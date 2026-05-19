<?php

declare(strict_types=1);

namespace Domain\Contact\ValueObjects;

use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Stringable;

final readonly class PhoneNumber implements Stringable
{
    private const string PATTERN_E164 = '/^\+\d+$/';

    private const string PATTERN_AU_NZ = '/^\+(?<region>61|64)\d{8,10}$/';

    private const string REGION_AU = 'AU';

    private const string REGION_NZ = 'NZ';

    public string $value;

    public string $region;

    public function __construct(string $value)
    {
        $this->guardMalformed($value);
        $this->guardUnsupportedRegion($value);
        $this->guardE164ForSupportedRegion($value);

        $this->value = $value;
        $this->region = str_starts_with($value, '+61') ? self::REGION_AU : self::REGION_NZ;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function region(): string
    {
        return $this->region;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function guardMalformed(string $value): void
    {
        if (preg_match(self::PATTERN_E164, $value) !== 1) {
            if (str_starts_with($value, '+')) {
                throw InvalidPhoneNumberException::malformed($value);
            }

            throw InvalidPhoneNumberException::notE164($value);
        }
    }

    private function guardUnsupportedRegion(string $value): void
    {
        $hasSupportedPrefix = str_starts_with($value, '+61') || str_starts_with($value, '+64');

        if (! $hasSupportedPrefix) {
            throw InvalidPhoneNumberException::unsupportedRegion($value);
        }
    }

    private function guardE164ForSupportedRegion(string $value): void
    {
        if (preg_match(self::PATTERN_AU_NZ, $value) !== 1) {
            throw InvalidPhoneNumberException::notE164($value);
        }
    }
}
