# Componenta Phone

Phone number value object backed by `giggsey/libphonenumber-for-php`.

Use it when phone numbers should be validated, normalized to E.164, formatted for UI, and serialized as a stable string.

## Installation

```bash
composer require componenta/phone
```

## Related Packages

This package is standalone but relies on `giggsey/libphonenumber-for-php`.

| Package | Why it may be used nearby |
|---|---|
| `componenta/validation` | Validates raw phone input before creating `Phone`. |
| `componenta/auth` | Can use phone numbers for OTP or passwordless flows. |
| `componenta/cqrs` | Registration/profile commands can type phone fields with this value object. |

## Usage

```php
use Componenta\Stdlib\Phone;

$phone = Phone::create('916 123-45-67', region: 'RU');

(string) $phone;          // "+79161234567"
$phone->toInternational();
$phone->toNational();
$phone->toRfc3966();
$phone->countryCode();    // 7
$phone->detectedRegion(); // "RU"
```

## Constructors

- `Phone::create($phone, $region = 'RU')` parses and validates raw input
- `Phone::fromE164($e164)` creates from a pre-normalized E.164 string and still validates it
- `Phone::tryCreate($phone, $region = 'RU')` returns `null` instead of throwing

The region is only a parsing hint for local formats. Numbers starting with `+` are parsed region-agnostically. The stored value is always E.164.

## Validation And Errors

Invalid input throws `InvalidArgumentException`. Static `isValid()` can be used for boolean checks when no value object is needed.

## Serialization

`__toString()` and `jsonSerialize()` return the E.164 value. `masked()` preserves the country code and the last four digits for logs and UI.

## Performance

The parsed `PhoneNumber` instance is created lazily and cached on the value object after the first formatting/classification call.
