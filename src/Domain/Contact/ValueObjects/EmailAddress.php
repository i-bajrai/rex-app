<?php

declare(strict_types=1);

namespace Domain\Contact\ValueObjects;

use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    private const int MAX_LENGTH = 254;

    public string $value;

    public string $domain;

    public function __construct(string $value)
    {
        $normalised = mb_strtolower($value);

        $this->guardLength($normalised);
        $this->guardRfc($normalised);

        $this->value = $normalised;
        $this->domain = $this->extractDomain($normalised);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function guardLength(string $value): void
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidEmailAddressException::tooLong($value);
        }
    }

    private function guardRfc(string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddressException::invalid($value);
        }
    }

    private function extractDomain(string $value): string
    {
        $atPosition = mb_strrpos($value, '@');

        if ($atPosition === false) {
            throw InvalidEmailAddressException::invalid($value);
        }

        return mb_substr($value, $atPosition + 1);
    }
}
