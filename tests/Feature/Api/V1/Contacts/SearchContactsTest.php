<?php

declare(strict_types=1);

use App\Models\Contact;

test('GET /api/v1/contacts/search?name= matches contacts by case-insensitive name prefix', function (): void {
    $match = Contact::factory()->withPhone()->create(['name' => 'Jane Doe']);
    Contact::factory()->withPhone()->create(['name' => 'Bob Smith']);

    $response = $this->getJson('/api/v1/contacts/search?name=jan');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('GET /api/v1/contacts/search?phone= matches a contact by exact E164 phone', function (): void {
    $match = Contact::factory()->withPhone('+61412345678')->create();
    Contact::factory()->withPhone('+61412345679')->create();

    $response = $this->getJson('/api/v1/contacts/search?phone=%2B61412345678');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('GET /api/v1/contacts/search?email= matches a contact by exact email address', function (): void {
    $match = Contact::factory()->withEmail('jane@example.com')->create();
    Contact::factory()->withEmail('someone-else@example.com')->create();

    $response = $this->getJson('/api/v1/contacts/search?email=jane@example.com');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('GET /api/v1/contacts/search?email= matches case-insensitively against the normalised address', function (): void {
    $match = Contact::factory()->withEmail('jane@example.com')->create();
    Contact::factory()->withEmail('someone-else@example.com')->create();

    $response = $this->getJson('/api/v1/contacts/search?email=JANE@EXAMPLE.COM');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('GET /api/v1/contacts/search combines name and email with AND', function (): void {
    $match = Contact::factory()
        ->withEmail('jane@example.com')
        ->create(['name' => 'Jane Smith']);
    Contact::factory()
        ->withEmail('jan@example.com')
        ->create(['name' => 'Janelle']);
    Contact::factory()
        ->withEmail('jane@example.com.au')
        ->create(['name' => 'Bob']);

    $response = $this->getJson('/api/v1/contacts/search?name=jan&email=jane@example.com');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('GET /api/v1/contacts/search with no criteria returns 422 contact.search.no_criteria', function (): void {
    $response = $this->getJson('/api/v1/contacts/search');

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.search.no_criteria');
});

test('GET /api/v1/contacts/search rejects an invalid phone with 422 contact.phone.not_e164', function (): void {
    $response = $this->getJson('/api/v1/contacts/search?phone=0412345678');

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.not_e164');
});

test('GET /api/v1/contacts/search rejects an unsupported region phone with 422 contact.phone.unsupported_region', function (): void {
    $response = $this->getJson('/api/v1/contacts/search?phone=%2B14155551234');

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.unsupported_region');
});
