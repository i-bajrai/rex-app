<?php

declare(strict_types=1);

use App\Models\Contact;

test('contact:delete removes the contact and prints a confirmation line', function (): void {
    $contact = Contact::factory()->withPhone()->create();

    $this->artisan('contact:delete', ['id' => $contact->id])
        ->expectsOutputToContain(sprintf('Contact %d deleted.', $contact->id))
        ->assertExitCode(0);

    expect(Contact::query()->find($contact->id))->toBeNull();
});

test('contact:delete is idempotent when the contact does not exist', function (): void {
    $this->artisan('contact:delete', ['id' => 999_999])
        ->expectsOutputToContain('Contact 999999 deleted.')
        ->assertExitCode(0);
});
