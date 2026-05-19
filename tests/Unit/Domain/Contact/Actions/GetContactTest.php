<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Actions\GetContact;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Pest\Expectation;

test('returns the contact with eager-loaded phones and emails', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create();

    $result = resolve(GetContact::class)->execute($contact->id);

    expect($result->id)->toBe($contact->id)
        ->and($result->relationLoaded('phones'))->toBeTrue()
        ->and($result->relationLoaded('emails'))->toBeTrue()
        ->and($result->phones)->toHaveCount(1)
        ->and($result->emails)->toHaveCount(1);
});

test('throws ContactNotFoundException when the contact id is missing', function (): void {
    expect(fn () => resolve(GetContact::class)->execute(987_654))
        ->toThrow(fn (ContactNotFoundException $e): Expectation => expect($e->errorCode)->toBe('contact.not_found')
            ->and($e->id)->toBe(987_654));
});
