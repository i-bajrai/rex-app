<?php

declare(strict_types=1);

use App\Models\Contact;
use Domain\Contact\Actions\SearchContacts;
use Domain\Contact\DataTransferObjects\ContactSearchCriteria;
use Domain\Contact\Exceptions\NoSearchCriteriaException;
use Domain\Contact\ValueObjects\PhoneNumber;

test('matches contacts whose name has the supplied case-insensitive prefix', function (): void {
    $match = Contact::factory()->withPhone()->create(['name' => 'Jane Doe']);
    Contact::factory()->withPhone()->create(['name' => 'Bob Smith']);

    $results = resolve(SearchContacts::class)->execute(new ContactSearchCriteria(name: 'jan'));

    expect($results->pluck('id')->all())->toBe([$match->id]);
});

test('matches contacts whose phone equals the supplied E164 value exactly', function (): void {
    $match = Contact::factory()->withPhone('+61412345678')->create();
    Contact::factory()->withPhone('+61412345679')->create();

    $results = resolve(SearchContacts::class)->execute(new ContactSearchCriteria(
        phone: new PhoneNumber('+61412345678'),
    ));

    expect($results->pluck('id')->all())->toBe([$match->id]);
});

test('matches contacts whose email equals the supplied address exactly, ignoring case', function (): void {
    $match = Contact::factory()->withEmail('jane@example.com')->create();
    Contact::factory()->withEmail('someone-else@example.com')->create();

    $results = resolve(SearchContacts::class)->execute(new ContactSearchCriteria(email: 'JANE@Example.COM'));

    expect($results->pluck('id')->all())->toBe([$match->id]);
});

test('does not match contacts that share the same domain when searching by exact email', function (): void {
    $match = Contact::factory()->withEmail('jane@example.com')->create();
    Contact::factory()->withEmail('someone-else@example.com')->create();

    $results = resolve(SearchContacts::class)->execute(new ContactSearchCriteria(email: 'jane@example.com'));

    expect($results->pluck('id')->all())->toBe([$match->id]);
});

test('combines multiple criteria with AND semantics', function (): void {
    $match = Contact::factory()
        ->withEmail('jane@example.com')
        ->create(['name' => 'Jane Smith']);

    Contact::factory()
        ->withEmail('jan@example.com')
        ->create(['name' => 'Janelle Other']);

    Contact::factory()
        ->withEmail('jane@example.com.au')
        ->create(['name' => 'Bob Baker']);

    $results = resolve(SearchContacts::class)->execute(new ContactSearchCriteria(
        name: 'jan',
        email: 'jane@example.com',
    ));

    expect($results->pluck('id')->all())->toBe([$match->id]);
});

test('rejects an empty criteria set', function (): void {
    expect(fn () => resolve(SearchContacts::class)->execute(new ContactSearchCriteria))
        ->toThrow(NoSearchCriteriaException::class);
});
