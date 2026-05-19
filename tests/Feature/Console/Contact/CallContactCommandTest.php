<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\Exceptions\ContactHasNoPhoneException;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Artisan;

test('contact:call prints the call outcome as JSON', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    $this->app->instance(TelephonyGateway::class, new readonly class implements TelephonyGateway
    {
        public function call(PhoneNumber $to): CallOutcome
        {
            return new CallOutcome(CallStatus::Connected, 90, 'connected');
        }
    });

    $exit = Artisan::call('contact:call', ['id' => $contact->id]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('"status": "connected"')
        ->and($output)->toContain('"duration_seconds": 90');
});

test('contact:call propagates ContactHasNoPhoneException when the contact has no phones', function (): void {
    $contact = Contact::factory()->withEmail('jane@example.com')->create();

    expect(fn () => $this->artisan('contact:call', ['id' => $contact->id]))
        ->toThrow(ContactHasNoPhoneException::class);
});
