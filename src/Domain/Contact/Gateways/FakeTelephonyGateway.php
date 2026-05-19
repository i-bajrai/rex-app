<?php

declare(strict_types=1);

namespace Domain\Contact\Gateways;

use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\ValueObjects\PhoneNumber;
use Random\Engine\Mt19937;
use Random\Randomizer;

final readonly class FakeTelephonyGateway implements TelephonyGateway
{
    private const int DEFAULT_SEED = 4_242;

    private const int MIN_DURATION_SECONDS = 3;

    private const int MAX_DURATION_SECONDS = 600;

    private Randomizer $randomizer;

    public function __construct(int $seed = self::DEFAULT_SEED)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function call(PhoneNumber $to): CallOutcome
    {
        $bucket = $this->randomizer->getInt(0, 4);

        return match ($bucket) {
            0 => new CallOutcome(
                status: CallStatus::Connected,
                durationSeconds: $this->randomizer->getInt(self::MIN_DURATION_SECONDS, self::MAX_DURATION_SECONDS),
                providerMessage: sprintf('Connected to %s.', (string) $to),
            ),
            1 => new CallOutcome(CallStatus::NoAnswer, null, 'No answer from destination.'),
            2 => new CallOutcome(CallStatus::Busy, null, 'Destination line is busy.'),
            3 => new CallOutcome(CallStatus::Failed, null, 'Carrier reported a failure.'),
            default => new CallOutcome(CallStatus::InvalidNumber, null, 'Destination number is invalid.'),
        };
    }
}
