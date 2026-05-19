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

        $data = [
            'id' => $contact->id,
            'name' => $contact->name,
            'created_at' => $contact->created_at->toIso8601String(),
            'updated_at' => $contact->updated_at->toIso8601String(),
        ];

        if ($contact->relationLoaded('phones')) {
            $data['phones'] = $contact->phones->map(fn (ContactPhone $phone): string => (string) $phone->e164)->values()->all();
        }

        if ($contact->relationLoaded('emails')) {
            $data['emails'] = $contact->emails->map(fn (ContactEmail $email): string => (string) $email->address)->values()->all();
        }

        $attributes = $contact->getAttributes();

        if (array_key_exists('phones_count', $attributes)) {
            $phonesCount = $attributes['phones_count'];
            $data['phones_count'] = is_numeric($phonesCount) ? (int) $phonesCount : 0;
        }

        if (array_key_exists('emails_count', $attributes)) {
            $emailsCount = $attributes['emails_count'];
            $data['emails_count'] = is_numeric($emailsCount) ? (int) $emailsCount : 0;
        }

        return $data;
    }
}
