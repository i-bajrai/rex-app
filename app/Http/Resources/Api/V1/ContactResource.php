<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Contact $resource
 */
final class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'created_at' => $contact->created_at->toIso8601String(),
            'updated_at' => $contact->updated_at->toIso8601String(),
            'phones' => $this->whenLoaded(
                'phones',
                fn () => $contact->phones->map(fn (ContactPhone $phone): string => (string) $phone->e164)->values()->all(),
            ),
            'emails' => $this->whenLoaded(
                'emails',
                fn () => $contact->emails->map(fn (ContactEmail $email): string => (string) $email->address)->values()->all(),
            ),
            'phones_count' => $this->whenCounted('phones', fn (int $count): int => $count),
            'emails_count' => $this->whenCounted('emails', fn (int $count): int => $count),
        ];
    }
}
