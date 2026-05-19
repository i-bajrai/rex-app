<?php

declare(strict_types=1);

use App\Models\Contact;

it('renders the empty state on the list when no contacts exist', function (): void {
    $page = visit('/contacts');

    $page->assertSee('Contacts')
        ->assertSee('No contacts yet');
});

it('renders contacts on the list when some exist', function (): void {
    Contact::factory()->withPhone('+61412345678')->create(['name' => 'Jane Doe']);
    Contact::factory()->withEmail('alex@example.com')->create(['name' => 'Alex Wong']);

    $page = visit('/contacts');

    $page->assertSee('Jane Doe')
        ->assertSee('Alex Wong');
});

it('shows a scoped empty state when search yields no matches', function (): void {
    Contact::factory()->withEmail('alex@example.com')->create(['name' => 'Alex']);

    $page = visit('/contacts');

    $page->type('input[name=name]', 'zzz')
        ->click('Search')
        ->assertSee('No contacts matched');
});

it('shows inline zod errors when phone is not E164 on create', function (): void {
    $page = visit('/contacts/new');

    $page->type('#name', 'Jane Doe')
        ->type('input[name="phones.0.value"]', '0412345678')
        ->click('Create contact')
        ->assertSee('Must be E164');
});

it('maps duplicate-phone server errors inline on create', function (): void {
    Contact::factory()->withPhone('+61412345678')->create(['name' => 'Existing']);

    $page = visit('/contacts/new');

    $page->type('#name', 'Jane Doe')
        ->type('input[name="phones.0.value"]', '+61412345678')
        ->click('Create contact')
        ->assertSee('already');
});

it('rejects within-payload duplicate emails inline before submit', function (): void {
    $page = visit('/contacts/new');

    $page->type('#name', 'Jane Doe')
        ->type('input[name="emails.0.value"]', 'dup@example.com')
        ->click('Add email')
        ->type('input[name="emails.1.value"]', 'DUP@example.com')
        ->click('Create contact')
        ->assertSee('duplicated in your submission');

    expect(Contact::query()->count())->toBe(0);
});

it('places a call from the show page and renders an outcome panel', function (): void {
    $contact = Contact::factory()
        ->withPhone('+61412345678')
        ->create(['name' => 'Caller Target']);

    $page = visit(sprintf('/contacts/%d', $contact->id));

    $page->assertSee('Caller Target')
        ->assertSee('+61412345678')
        ->click('Place Call');
});

it('disables the call button when the contact has no phones', function (): void {
    $contact = Contact::factory()
        ->withEmail('alex@example.com')
        ->create(['name' => 'Email Only']);

    $page = visit(sprintf('/contacts/%d', $contact->id));

    $page->assertSee('No phone on file');
});
