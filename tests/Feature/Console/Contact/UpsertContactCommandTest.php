<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;

test('contact:upsert creates a new contact and prints the contact id', function (): void {
    $this->artisan('contact:upsert', [
        '--name' => 'Jane Doe',
        '--phone' => ['+61412345678'],
        '--email' => ['jane@example.com'],
    ])
        ->expectsOutputToContain('Contact upserted: id=')
        ->assertExitCode(0);

    expect(Contact::query()->where('name', 'Jane Doe')->count())->toBe(1);
});

test('contact:upsert dispatches the same domain exception for an invalid phone as HTTP', function (): void {
    expect(fn () => $this->artisan('contact:upsert', [
        '--name' => 'Jane',
        '--phone' => ['0412345678'],
    ]))->toThrow(InvalidPhoneNumberException::class);
});
