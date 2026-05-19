<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\ValueObjects\PhoneNumber;

test('POST /api/v1/contacts/{id}/call invokes the gateway and returns the outcome', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    $this->app->instance(TelephonyGateway::class, new readonly class implements TelephonyGateway
    {
        public function call(PhoneNumber $to): CallOutcome
        {
            return new CallOutcome(CallStatus::Connected, 90, 'connected ok');
        }
    });

    $response = $this->postJson(sprintf('/api/v1/contacts/%d/call', $contact->id));

    $response->assertOk()
        ->assertJsonPath('data.status', 'connected')
        ->assertJsonPath('data.duration_seconds', 90)
        ->assertJsonPath('data.provider_message', 'connected ok');
});

test('non-connected outcome includes null duration', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    $this->app->instance(TelephonyGateway::class, new readonly class implements TelephonyGateway
    {
        public function call(PhoneNumber $to): CallOutcome
        {
            return new CallOutcome(CallStatus::NoAnswer, null, 'no answer');
        }
    });

    $response = $this->postJson(sprintf('/api/v1/contacts/%d/call', $contact->id));

    $response->assertOk()
        ->assertJsonPath('data.status', 'no_answer')
        ->assertJsonPath('data.duration_seconds', null);
});

test('POST /api/v1/contacts/{id}/call returns 422 contact.call.no_phone for a contact with no phones', function (): void {
    $contact = Contact::factory()->withEmail('jane@example.com')->create();

    $response = $this->postJson(sprintf('/api/v1/contacts/%d/call', $contact->id));

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.call.no_phone');
});

test('POST /api/v1/contacts/{id}/call returns 404 when the contact does not exist', function (): void {
    $response = $this->postJson('/api/v1/contacts/999999/call');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found');
});
