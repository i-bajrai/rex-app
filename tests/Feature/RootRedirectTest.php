<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

test('GET / redirects to /contacts', function (): void {
    $response = $this->get('/');

    $response->assertStatus(Response::HTTP_FOUND)
        ->assertRedirect('/contacts');
});
