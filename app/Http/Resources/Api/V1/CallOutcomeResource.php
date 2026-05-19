<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Domain\Contact\DataTransferObjects\CallOutcome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CallOutcome $resource
 */
final class CallOutcomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'duration_seconds' => $this->resource->durationSeconds,
            'provider_message' => $this->resource->providerMessage,
        ];
    }
}
