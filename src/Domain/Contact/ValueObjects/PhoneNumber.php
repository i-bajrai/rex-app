<?php

declare(strict_types=1);

namespace Domain\Contact\ValueObjects;

use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Stringable;

final readonly class PhoneNumber implements Stringable
{
    private const string PATTERN_E164 = '/^\+\d+$/';

    private const string PATTERN_AU_NZ = '/^\+(?<region>61|64)\d{8,10}$/';

    /** @var array<string, string> */
    private const array REGION_BY_PREFIX = [
        '61' => 'AU',
        '64' => 'NZ',
    ];

    public string $value;

    public string $region;

    public function __construct(string $value)
    {
        $this->guardMalformed($value);
        $this->guardUnsupportedRegion($value);
        $this->guardE164ForSupportedRegion($value);

        $matches = [];
        preg_match(self::PATTERN_AU_NZ, $value, $matches);

        $this->value = $value;
        $this->region = self::REGION_BY_PREFIX[$matches['region']];
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function region(): string
    {
        return $this->region;
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
