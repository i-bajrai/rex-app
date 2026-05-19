<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Illuminate\Support\Facades\Artisan;

test('contact:show prints the contact as JSON', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->withEmail('jane@example.com')
        ->create(['name' => 'Jane']);

    $exit = Artisan::call('contact:show', ['id' => $contact->id]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('"name": "Jane"')
        ->and($output)->toContain('+61412345678')
        ->and($output)->toContain('jane@example.com');
});

test('contact:show propagates ContactNotFoundException for a missing id', function (): void {
    expect(fn () => $this->artisan('contact:show', ['id' => 999_999]))
        ->toThrow(ContactNotFoundException::class);
});
