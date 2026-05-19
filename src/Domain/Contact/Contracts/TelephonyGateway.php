<?php

declare(strict_types=1);

namespace Domain\Contact\Contracts;

use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\ValueObjects\PhoneNumber;

interface TelephonyGateway
{
    public function call(PhoneNumber $to): CallOutcome;
}
