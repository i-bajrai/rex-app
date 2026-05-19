<?php

declare(strict_types=1);

use App\Models\Contact;

test('POST /api/v1/contacts creates a new contact and returns 201 with Location header', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane Doe',
        'phones' => ['+61412345678'],
        'emails' => ['jane@example.com'],
    ]);

    $response->assertStatus(201);

    $id = $response->json('data.id');

    $response->assertHeader('Location', sprintf('/api/v1/contacts/%d', $id));

    expect($response->json('data'))->toMatchArray([
        'id' => $id,
        'name' => 'Jane Doe',
        'phones' => ['+61412345678'],
        'emails' => ['jane@example.com'],
    ]);
});

test('PUT /api/v1/contacts/{id} updates an existing contact and returns 200', function (): void {
    $existing = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('old@example.com')
        ->create();

    $response = $this->putJson(sprintf('/api/v1/contacts/%d', $existing->id), [
        'name' => 'New Name',
        'phones' => ['+64211234567'],
        'emails' => ['new@example.com'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.phones.0', '+64211234567')
        ->assertJsonPath('data.emails.0', 'new@example.com');
});

test('PUT /api/v1/contacts/{id} returns 404 when the contact does not exist', function (): void {
    $response = $this->putJson('/api/v1/contacts/999999', [
        'name' => 'X',
        'phones' => ['+61412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found')
        ->assertJsonPath('error.details.id', 999_999);
});

test('POST /api/v1/contacts returns 422 contact.phone.not_e164 for non-E164 phone', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => ['0412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.not_e164')
        ->assertJsonPath('error.details.phone', '0412345678');
});

test('POST /api/v1/contacts returns 422 contact.phone.unsupported_region for non-AU/NZ phone', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => ['+14155551234'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.unsupported_region')
        ->assertJsonPath('error.details.phone', '+14155551234')
        ->assertJsonPath('error.details.supported_regions', ['AU', 'NZ']);
});

test('POST /api/v1/contacts returns 422 contact.email.invalid for malformed email', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => [],
        'emails' => ['not-an-email'],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.email.invalid')
        ->assertJsonPath('error.details.email', 'not-an-email');
});

test('POST /api/v1/contacts returns 422 contact.phone.duplicate when phone already exists', function (): void {
    Contact::factory()->withPhone('+61412345678')->create();

    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Other',
        'phones' => ['+61412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.duplicate')
        ->assertJsonPath('error.details.phone', '+61412345678');
});

test('POST /api/v1/contacts returns 422 contact.email.duplicate when email already exists', function (): void {
    Contact::factory()->withEmail('shared@example.com')->create();

    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Other',
        'phones' => [],
        'emails' => ['Shared@Example.com'],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.email.duplicate')
        ->assertJsonPath('error.details.email', 'shared@example.com');
});

test('POST /api/v1/contacts returns 422 contact.no_identifiers when there are no phones or emails', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Identifier-less',
        'phones' => [],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.no_identifiers');
});

test('POST /api/v1/contacts returns 422 validation_failed for missing name', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'phones' => ['+61412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $details = $response->json('error.details');

    expect($details)->toBeArray()
        ->and(collect($details)->pluck('field')->all())->toContain('name');
});

test('POST /api/v1/contacts rejects within-payload duplicate phones with validation_failed envelope', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => ['+61412345678', '+61412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $details = collect($response->json('error.details'));

    expect($details->firstWhere('field', 'phones.1'))->toMatchArray([
        'field' => 'phones.1',
        'code' => 'duplicate',
    ]);
});

test('POST /api/v1/contacts rejects within-payload duplicate emails (case-insensitive) with validation_failed envelope', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => [],
        'emails' => ['jane@example.com', 'Jane@Example.com'],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $details = collect($response->json('error.details'));

    expect($details->firstWhere('field', 'emails.1'))->toMatchArray([
        'field' => 'emails.1',
        'code' => 'duplicate',
    ]);
});

test('PUT /api/v1/contacts/{id} rejects within-payload duplicate phones before the DB write', function (): void {
    $existing = Contact::factory()
        ->withPhone('+61400000000')
        ->create();

    $response = $this->putJson(sprintf('/api/v1/contacts/%d', $existing->id), [
        'name' => 'Jane',
        'phones' => ['+61412345678', '+61412345678'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $details = collect($response->json('error.details'));

    expect($details->firstWhere('field', 'phones.1'))->toMatchArray([
        'field' => 'phones.1',
        'code' => 'duplicate',
    ]);

    expect($existing->fresh()->phones()->pluck('e164')->all())->toEqual(['+61400000000']);
});

test('PUT /api/v1/contacts/{id} rejects within-payload duplicate emails before the DB write', function (): void {
    $existing = Contact::factory()
        ->withEmail('old@example.com')
        ->create();

    $response = $this->putJson(sprintf('/api/v1/contacts/%d', $existing->id), [
        'name' => 'Jane',
        'phones' => [],
        'emails' => ['dup@example.com', 'DUP@example.com'],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $details = collect($response->json('error.details'));

    expect($details->firstWhere('field', 'emails.1'))->toMatchArray([
        'field' => 'emails.1',
        'code' => 'duplicate',
    ]);

    expect($existing->fresh()->emails()->pluck('address')->all())->toEqual(['old@example.com']);
});
