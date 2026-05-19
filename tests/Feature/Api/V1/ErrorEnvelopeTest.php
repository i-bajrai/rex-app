<?php

declare(strict_types=1);

use App\Models\Contact;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

test('field validation errors return a 422 with the validation_failed envelope and details list', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'phones' => 'not-an-array',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details' => [
                    '*' => ['field', 'code', 'message'],
                ],
            ],
        ]);
});

test('domain rejection error returns a 422 with the domain code and details map', function (): void {
    $response = $this->postJson('/api/v1/contacts', [
        'name' => 'Jane',
        'phones' => ['+14155551234'],
        'emails' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'contact.phone.unsupported_region')
        ->assertJsonPath('error.details.phone', '+14155551234')
        ->assertJsonPath('error.details.supported_regions', ['AU', 'NZ']);
});

test('404 returns the contact.not_found envelope with the requested id', function (): void {
    $response = $this->getJson('/api/v1/contacts/424242');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found')
        ->assertJsonPath('error.details.id', 424_242);
});

test('429 from the call endpoint returns the rate-limited envelope and Retry-After header', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    RateLimiter::clear(sprintf('api/v1/contacts/%d/call|127.0.0.1', $contact->id));

    foreach (range(1, 10) as $ignored) {
        $this->postJson(sprintf('/api/v1/contacts/%d/call', $contact->id))->assertOk();
    }

    $response = $this->postJson(sprintf('/api/v1/contacts/%d/call', $contact->id));

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'contact.call.rate_limited')
        ->assertHeader('Retry-After');

    $retry = (int) $response->headers->get('Retry-After');

    expect($response->json('error.details.retry_after_seconds'))->toBe($retry);
});

test('2xx responses are not wrapped in the error envelope', function (): void {
    $contact = Contact::factory()->withPhone('+61412345678')->create();

    $response = $this->getJson(sprintf('/api/v1/contacts/%d', $contact->id));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'phones', 'emails']])
        ->assertJsonMissingPath('error');
});

test('a non-numeric contact id returns the 404 envelope from the route layer', function (): void {
    $response = $this->getJson('/api/v1/contacts/not-a-number');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'contact.not_found')
        ->assertJsonPath('error.details.id', 0);
});

test('a non-API HTML 404 falls back to the framework default rather than the envelope', function (): void {
    $response = $this->get('/not-api/missing', ['Accept' => 'text/html']);

    expect($response->status())->toBe(404);

    $body = $response->getContent();

    expect(is_string($body) && str_contains($body, '"error"'))->toBeFalse();
});

test('generic http error mounted via Laravel abort is mapped to the envelope when targeting the API', function (): void {
    Route::middleware('api')
        ->prefix('api/v1')
        ->get('contacts-broken', fn () => App::abort(503, 'maintenance'));

    $response = $this->getJson('/api/v1/contacts-broken');

    $response->assertStatus(503)
        ->assertJsonPath('error.code', 'http_error')
        ->assertJsonPath('error.message', 'maintenance');
});
