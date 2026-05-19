<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactPhone;
use Domain\Contact\Actions\PlaceCallToContact;
use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\Exceptions\ContactHasNoPhoneException;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Domain\Contact\ValueObjects\PhoneNumber;

test('calls the gateway with the contacts primary phone (oldest by created_at)', function (): void {
    $contact = Contact::factory()->create();
    $older = ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345678',
        'created_at' => now()->subMinutes(10),
    ]);
    ContactPhone::factory()->recycle($contact)->create([
        'e164' => '+61412345679',
        'created_at' => now(),
    ]);

    $captured = null;
    $this->app->instance(TelephonyGateway::class, new class($captured) implements TelephonyGateway
    {
        public function __construct(private mixed &$captured) {}

        public function call(PhoneNumber $to): CallOutcome
        {
            $this->captured = (string) $to;

            return new CallOutcome(CallStatus::Connected, 42, 'ok');
        }
    });

    $outcome = resolve(PlaceCallToContact::class)->execute($contact->id);

    expect($captured)->toBe('+61412345678')
        ->and($outcome->status)->toBe(CallStatus::Connected)
        ->and($outcome->durationSeconds)->toBe(42)
        ->and($older->id)->toBeInt();
});

test('returns the gateway outcome verbatim', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    $expected = new CallOutcome(CallStatus::NoAnswer, null, 'no_answer');

    $this->app->instance(TelephonyGateway::class, new readonly class($expected) implements TelephonyGateway
    {
        public function __construct(private CallOutcome $outcome) {}

        public function call(PhoneNumber $to): CallOutcome
        {
            return $this->outcome;
        }
    });

    $outcome = resolve(PlaceCallToContact::class)->execute($contact->id);

    expect($outcome)->toBe($expected);
});

test('rejects a contact with zero phones without invoking the gateway', function (): void {
    $contact = Contact::factory()->withEmail('jane@example.com')->create();

    $invoked = false;
    $this->app->instance(TelephonyGateway::class, new class($invoked) implements TelephonyGateway
    {
        public function __construct(private bool &$invoked) {}

        public function call(PhoneNumber $to): CallOutcome
        {
            $this->invoked = true;

            return new CallOutcome(CallStatus::Failed);
        }
    });

    try {
        resolve(PlaceCallToContact::class)->execute($contact->id);
        $this->fail('expected ContactHasNoPhoneException');
    } catch (ContactHasNoPhoneException $contactHasNoPhoneException) {
        expect($contactHasNoPhoneException->errorCode)->toBe('contact.call.no_phone')
            ->and($invoked)->toBeFalse();
    }
});

test('throws ContactNotFoundException when the contact id is missing', function (): void {
    expect(fn () => resolve(PlaceCallToContact::class)->execute(987_654))
        ->toThrow(ContactNotFoundException::class);
});
