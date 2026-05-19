<?php

declare(strict_types=1);

namespace Domain\Contact\DataTransferObjects;

use Domain\Contact\Enums\CallStatus;

final readonly class CallOutcome
{
    public function __construct(
        public CallStatus $status,
        public ?int $durationSeconds = null,
        public ?string $providerMessage = null,
    ) {}
}
