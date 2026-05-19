<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactEmail;
use Domain\Contact\ValueObjects\EmailAddress;

test('contact belongs-to relationship returns the parent contact', function (): void {
    $contact = Contact::factory()->create();
    $email = ContactEmail::factory()->recycle($contact)->create([
        'address' => 'jane@example.com',
    ]);

    expect($email->contact()->first()?->id)->toBe($contact->id);
});

test('address attribute hydrates as an EmailAddress value object', function (): void {
    $contact = Contact::factory()->create();
    $email = ContactEmail::factory()->recycle($contact)->create([
        'address' => 'jane@example.com',
    ])->refresh();

    expect($email->address)->toBeInstanceOf(EmailAddress::class)
        ->and((string) $email->address)->toBe('jane@example.com')
        ->and($email->address_domain)->toBe('example.com');
});
