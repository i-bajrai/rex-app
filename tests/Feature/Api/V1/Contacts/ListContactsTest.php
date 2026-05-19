<?php

declare(strict_types=1);

use App\Models\Contact;

test('GET /api/v1/contacts returns contacts newest first with phone and email counts', function (): void {
    $older = Contact::factory()->withPhone('+61412345678')->create([
        'name' => 'Old',
        'created_at' => now()->subHour(),
    ]);
    $newer = Contact::factory()
        ->withEmail('new@example.com')
        ->create([
            'name' => 'New',
            'created_at' => now(),
        ]);

    $response = $this->getJson('/api/v1/contacts');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.phones_count', 0)
        ->assertJsonPath('data.0.emails_count', 1)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.phones_count', 1);
});

test('GET /api/v1/contacts returns 200 with empty data when no contacts exist', function (): void {
    $response = $this->getJson('/api/v1/contacts');

    $response->assertOk()
        ->assertExactJson(['data' => []]);
});
