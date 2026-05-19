<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Exceptions\NoSearchCriteriaException;
use Illuminate\Support\Facades\Artisan;

test('contact:search prints matching contacts as JSON', function (): void {
    $match = Contact::factory()
        ->withEmail('jane@example.com')
        ->create(['name' => 'Jane']);

    $exit = Artisan::call('contact:search', ['--email-domain' => 'example.com']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain(sprintf('"id": %d', $match->id))
        ->and($output)->toContain('"name": "Jane"');
});

test('contact:search rejects empty criteria the same way as HTTP', function (): void {
    expect(fn () => $this->artisan('contact:search'))
        ->toThrow(NoSearchCriteriaException::class);
});
