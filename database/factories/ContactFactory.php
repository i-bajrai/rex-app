<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }

    public function withPhone(?string $e164 = null): self
    {
        return $this->has(
            ContactPhoneFactory::new()->state(fn (): array => [
                'e164' => $e164 ?? '+614'.fake()->unique()->numerify('########'),
            ]),
            'phones',
        );
    }

    public function withEmail(?string $address = null): self
    {
        return $this->has(
            ContactEmailFactory::new()->state(fn (): array => [
                'address' => $address ?? fake()->unique()->safeEmail(),
            ]),
            'emails',
        );
    }
}
