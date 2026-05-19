<?php

declare(strict_types=1);

namespace Domain\Contact\Enums;

enum CallStatus: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Failed = 'failed';
    case InvalidNumber = 'invalid_number';
}
