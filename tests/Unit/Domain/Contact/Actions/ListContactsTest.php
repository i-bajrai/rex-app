<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Actions\ListContacts;

test('returns contacts newest-first with phones_count and emails_count', function (): void {
    $older = Contact::factory()->withPhone()->create(['created_at' => now()->subHour()]);
    $newer = Contact::factory()->withEmail()->create(['created_at' => now()]);

    $contacts = resolve(ListContacts::class)->execute();

    expect($contacts->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($contacts->first()?->relationLoaded('phones'))->toBeFalse()
        ->and($contacts->first()?->getAttribute('phones_count'))->toBe(0)
        ->and($contacts->first()?->getAttribute('emails_count'))->toBe(1)
        ->and($contacts->last()?->getAttribute('phones_count'))->toBe(1);
});

test('caps the result at the supplied limit', function (): void {
    Contact::factory()->count(3)->withPhone()->create();

    $contacts = resolve(ListContacts::class)->execute(limit: 2);

    expect($contacts)->toHaveCount(2);
});

test('returns an empty collection when no contacts exist', function (): void {
    $contacts = resolve(ListContacts::class)->execute();

    expect($contacts->all())->toBe([]);
});
