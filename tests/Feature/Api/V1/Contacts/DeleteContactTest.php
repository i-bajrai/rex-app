<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;

test('DELETE /api/v1/contacts/{id} deletes a contact and cascades children, returning 204', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create();

    $response = $this->deleteJson(sprintf('/api/v1/contacts/%d', $contact->id));

    $response->assertNoContent();

    expect(Contact::query()->find($contact->id))->toBeNull()
        ->and(ContactPhone::query()->where('contact_id', $contact->id)->count())->toBe(0)
        ->and(ContactEmail::query()->where('contact_id', $contact->id)->count())->toBe(0);
});

test('DELETE /api/v1/contacts/{id} returns 404 contact.not_found when the contact has never existed', function (): void {
    $response = $this->deleteJson('/api/v1/contacts/999999');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found')
        ->assertJsonPath('error.details.id', 999_999);
});
