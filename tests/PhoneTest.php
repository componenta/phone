<?php

declare(strict_types=1);

namespace Componenta\Stdlib\Tests;

use Componenta\Stdlib\Phone;
use libphonenumber\PhoneNumberFormat;

it('normalizes valid phone numbers to E164', function (): void {
    $phone = Phone::create('(415) 555-2671', 'US');

    expect((string) $phone)->toBe('+14155552671')
        ->and($phone->value)->toBe('+14155552671')
        ->and($phone->countryCode())->toBe(1)
        ->and($phone->detectedRegion())->toBe('US')
        ->and($phone->format(PhoneNumberFormat::E164))->toBe('+14155552671');
});

it('rejects invalid phone numbers and offers nullable construction', function (): void {
    expect(fn () => Phone::create('123', 'US'))->toThrow(\InvalidArgumentException::class)
        ->and(Phone::tryCreate('123', 'US'))->toBeNull()
        ->and(Phone::isValid('(415) 555-2671', 'US'))->toBeTrue();
});

it('compares and masks normalized numbers', function (): void {
    $phone = Phone::fromE164('+14155552671');
    $same = Phone::create('+1 415 555 2671');

    expect($phone->equals($same))->toBeTrue()
        ->and($phone->masked())->toBe('+1******2671');
});
