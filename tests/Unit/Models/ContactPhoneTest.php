<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactPhone;
use Domain\Contact\ValueObjects\PhoneNumber;

test('contact belongs-to relationship returns the parent contact', function (): void {
    $contact = Contact::factory()->create();
    $phone = ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345678',
    ]);

    expect($phone->contact()->first()?->id)->toBe($contact->id);
});

test('e164 attribute hydrates as a PhoneNumber value object', function (): void {
    $contact = Contact::factory()->create();
    $phone = ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345678',
    ])->refresh();

    expect($phone->e164)->toBeInstanceOf(PhoneNumber::class)
        ->and((string) $phone->e164)->toBe('+61412345678');
});
