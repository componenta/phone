<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use InvalidArgumentException;
use JsonSerializable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;
use Stringable;

final class Phone implements Stringable, JsonSerializable
{
    /** Lazy cache populated on first parseStored() call. */
    private ?PhoneNumber $parsed = null;

    private function __construct(
        public readonly string $value,
    ) {}


    /**
     * Parses and validates a phone number string.
     * Region is used only as a parsing hint for local formats (e.g. "916 123-45-67").
     * Numbers starting with "+" are parsed region-agnostically.
     * The region is not stored; use detectedRegion() to get the actual region.
     * Stores the result in E164 format internally.
     *
     * @throws InvalidArgumentException If the number is empty, cannot be parsed, or is invalid.
     */
    public static function create(string $phone, string $region = 'RU'): self
    {
        $phone = trim($phone);

        if ($phone === '') {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }

        $util = self::util();

        try {
            $number = $util->parse($phone, strtoupper($region));

            if (!$util->isValidNumber($number)) {
                throw new InvalidArgumentException(
                    "Invalid phone number: {$phone}"
                );
            }

            return new self(
                $util->format($number, PhoneNumberFormat::E164),
            );
        } catch (NumberParseException $e) {
            throw new InvalidArgumentException(
                'Invalid phone number format: ' . $e->getMessage()
            );
        }
    }

    /**
     * Creates an instance from a pre-validated E164 string (e.g. from database).
     * Validates through libphonenumber to preserve invariants, not just a regex check.
     *
     * @throws InvalidArgumentException If the E164 value cannot be parsed or is invalid.
     */
    public static function fromE164(string $e164): self
    {
        $util = self::util();

        try {
            $number = $util->parse($e164, null);

            if (!$util->isValidNumber($number)) {
                throw new InvalidArgumentException(
                    "Invalid E164 phone number: {$e164}"
                );
            }

            return new self(
                $util->format($number, PhoneNumberFormat::E164)
            );
        } catch (NumberParseException $e) {
            throw new InvalidArgumentException(
                'Invalid phone number: ' . $e->getMessage()
            );
        }
    }

    /**
     * Returns null instead of throwing on invalid input.
     */
    public static function tryCreate(string $phone, string $region = 'RU'): ?self
    {
        try {
            return self::create($phone, $region);
        } catch (InvalidArgumentException) {
            return null;
        }
    }


    /**
     * Checks whether a raw string is a valid phone number for the given region.
     */
    public static function isValid(string $phone, string $region = 'RU'): bool
    {
        $phone = trim($phone);

        if ($phone === '') {
            return false;
        }

        $util = self::util();

        try {
            $number = $util->parse($phone, strtoupper($region));

            return $util->isValidNumber($number);
        } catch (NumberParseException) {
            return false;
        }
    }


    /**
     * Returns the number in the requested format.
     */
    public function format(PhoneNumberFormat $format = PhoneNumberFormat::INTERNATIONAL): string
    {
        return self::util()->format($this->parseStored(), $format);
    }

    /**
     * Returns the number in national format: "(916) 123-45-67"
     */
    public function toNational(): string
    {
        return $this->format(PhoneNumberFormat::NATIONAL);
    }

    /**
     * Returns the number in international format: "+7 916 123-45-67"
     */
    public function toInternational(): string
    {
        return $this->format(PhoneNumberFormat::INTERNATIONAL);
    }

    /**
     * Returns the number in RFC 3966 format suitable for tel: links: "tel:+7-916-123-45-67"
     */
    public function toRfc3966(): string
    {
        return $this->format(PhoneNumberFormat::RFC3966);
    }

    /**
     * Masks the number for logs and UI.
     * Preserves the country code prefix and last 4 digits, masks the middle.
     *
     * +7  -> "+7916***4567"   (prefix length 2)
     * +44 -> "+44791***3456"  (prefix length 3)
     * +1  -> "+1415***2671"   (prefix length 2)
     */
    public function masked(): string
    {
        $e164        = $this->value;
        $prefixLen   = strlen((string) $this->countryCode()) + 1; // +1 for the leading "+"
        $visibleTail = 4;
        $maskLen     = strlen($e164) - $prefixLen - $visibleTail;

        return substr($e164, 0, $prefixLen)
            . str_repeat('*', max(0, $maskLen))
            . substr($e164, -$visibleTail);
    }

    /**
     * Returns the numeric country calling code: "+79161234567" -> 7
     */
    public function countryCode(): int
    {
        return $this->parseStored()->getCountryCode();
    }

    /**
     * Returns the ISO 3166-1 alpha-2 region detected from the number itself.
     */
    public function detectedRegion(): ?string
    {
        $code = self::util()->getRegionCodeForNumber($this->parseStored());

        if ($code === null || $code === 'ZZ') {
            return null;
        }

        return $code;
    }


    /**
     * Compares two phone numbers by their normalized E164 value.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }


    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }


    /**
     * Parses the stored E164 value into a PhoneNumber object.
     * Result is cached; subsequent calls are free.
     * Wraps NumberParseException into RuntimeException because the stored value
     * must always be valid; if it's not, the data is corrupted.
     *
     * @throws RuntimeException If the stored E164 value cannot be parsed.
     */
    private function parseStored(): PhoneNumber
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        try {
            return $this->parsed = self::util()->parse($this->value, null);
        } catch (NumberParseException $e) {
            throw new RuntimeException(
                "Stored phone number is corrupted: {$this->value}",
                previous: $e,
            );
        }
    }

    /**
     * Returns the shared PhoneNumberUtil singleton.
     * Cached statically; getInstance() is cheap, but explicit is cleaner.
     */
    private static function util(): PhoneNumberUtil
    {
        static $util = null;

        return $util ??= PhoneNumberUtil::getInstance();
    }
}
