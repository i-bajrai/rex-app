<?php

declare(strict_types=1);

use App\Models\Contact;

test('GET /api/v1/contacts/{id} returns the contact with all phones and emails', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create(['name' => 'Jane']);

    $response = $this->getJson(sprintf('/api/v1/contacts/%d', $contact->id));

    $response->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.name', 'Jane')
        ->assertJsonPath('data.phones.0', '+61412345678')
        ->assertJsonPath('data.emails.0', 'jane@example.com');
});

test('GET /api/v1/contacts/{id} returns 404 contact.not_found when the contact is missing', function (): void {
    $response = $this->getJson('/api/v1/contacts/999999');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found')
        ->assertJsonPath('error.details.id', 999_999);
});
