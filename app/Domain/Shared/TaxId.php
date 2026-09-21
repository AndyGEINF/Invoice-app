<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Exceptions\InvalidTaxId;
use Stringable;

/**
 * Identificador fiscal: NIF, NIE o CIF español, o número de IVA de otro país de
 * la Unión Europea.
 *
 * Se valida el dígito o la letra de control, de modo que una errata al teclear
 * no acabe en una factura emitida. Los identificadores intracomunitarios solo
 * se comprueban de formato: su existencia real la confirma VIES, de forma
 * asíncrona y sin bloquear la emisión.
 */
final readonly class TaxId implements Stringable
{
    /** Letras de control del NIF y del NIE, en el orden del resto de dividir entre 23. */
    private const string NIF_CONTROL_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const int NIF_MODULUS = 23;

    /** Letras iniciales del NIE y el dígito por el que se sustituyen. */
    private const array NIE_PREFIXES = ['X' => '0', 'Y' => '1', 'Z' => '2'];

    /** Letras de control del CIF cuando el control es una letra. */
    private const string CIF_CONTROL_LETTERS = 'JABCDEFGHI';

    private const int CIF_MODULUS = 10;

    /** Formas jurídicas cuyo control es siempre una letra. */
    private const string CIF_LETTER_ONLY = 'PQRSNW';

    /** Formas jurídicas cuyo control es siempre un dígito. */
    private const string CIF_DIGIT_ONLY = 'ABEH';

    private const string PATTERN_NIF = '/^\d{8}[A-Z]$/';

    private const string PATTERN_NIE = '/^[XYZ]\d{7}[A-Z]$/';

    private const string PATTERN_CIF = '/^[ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J]$/';

    public const string COUNTRY_SPAIN = 'ES';

    /**
     * Formato del número de IVA por país de la UE, sin el prefijo.
     *
     * XI es Irlanda del Norte, que mantiene número europeo tras el Brexit.
     */
    private const array EU_VAT_PATTERNS = [
        'AT' => '/^U\d{8}$/',
        'BE' => '/^[01]\d{9}$/',
        'BG' => '/^\d{9,10}$/',
        'CY' => '/^\d{8}[A-Z]$/',
        'CZ' => '/^\d{8,10}$/',
        'DE' => '/^\d{9}$/',
        'DK' => '/^\d{8}$/',
        'EE' => '/^\d{9}$/',
        'EL' => '/^\d{9}$/',
        'ES' => '/^[A-Z0-9]\d{7}[A-Z0-9]$/',
        'FI' => '/^\d{8}$/',
        'FR' => '/^[A-Z0-9]{2}\d{9}$/',
        'HR' => '/^\d{11}$/',
        'HU' => '/^\d{8}$/',
        'IE' => '/^(\d{7}[A-W]|[7-9][A-Z*+]\d{5}[A-W]|\d{7}[A-W][AH])$/',
        'IT' => '/^\d{11}$/',
        'LT' => '/^(\d{9}|\d{12})$/',
        'LU' => '/^\d{8}$/',
        'LV' => '/^\d{11}$/',
        'MT' => '/^\d{8}$/',
        'NL' => '/^\d{9}B\d{2}$/',
        'PL' => '/^\d{10}$/',
        'PT' => '/^\d{9}$/',
        'RO' => '/^\d{2,10}$/',
        'SE' => '/^\d{12}$/',
        'SI' => '/^\d{8}$/',
        'SK' => '/^\d{10}$/',
        'XI' => '/^(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/',
    ];

    private function __construct(
        public string $value,
        public TaxIdType $type,
        public string $country,
    ) {}

    /**
     * Normaliza el valor y deduce el tipo cuando no se indica.
     *
     * No lanza excepción si el identificador es incorrecto: eso lo decide quien
     * llama, con {@see isValid()} o {@see valid()}.
     */
    public static function of(string $value, ?TaxIdType $type = null): self
    {
        $normalized = self::normalize($value);
        $resolvedType = $type ?? self::detectType($normalized);

        return new self($normalized, $resolvedType, self::detectCountry($normalized, $resolvedType));
    }

    /** Igual que {@see of()}, pero exige que el identificador sea válido. */
    public static function valid(string $value, ?TaxIdType $type = null): self
    {
        $taxId = self::of($value, $type);

        if (! $taxId->isValid()) {
            throw InvalidTaxId::forValue($value);
        }

        return $taxId;
    }

    public function isValid(): bool
    {
        if ($this->value === '') {
            return false;
        }

        return match ($this->type) {
            TaxIdType::NIF => self::isValidNif($this->value),
            TaxIdType::NIE => self::isValidNie($this->value),
            TaxIdType::CIF => self::isValidCif($this->value),
            TaxIdType::VAT_EU => self::isValidEuVat($this->value),
            TaxIdType::OTHER => true,
        };
    }

    /** Español, ya sea por tipo nacional o por número de IVA con prefijo ES. */
    public function isSpanish(): bool
    {
        return $this->country === self::COUNTRY_SPAIN;
    }

    public function isEuVat(): bool
    {
        return $this->type === TaxIdType::VAT_EU;
    }

    /** Identificador sin el prefijo de país, tal como se imprime en la factura. */
    public function nationalNumber(): string
    {
        if ($this->type === TaxIdType::VAT_EU) {
            return substr($this->value, strlen($this->country));
        }

        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** Mayúsculas y sin espacios, puntos ni guiones. */
    private static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    private static function detectType(string $value): TaxIdType
    {
        if (preg_match(self::PATTERN_NIF, $value) === 1) {
            return TaxIdType::NIF;
        }

        if (preg_match(self::PATTERN_NIE, $value) === 1) {
            return TaxIdType::NIE;
        }

        if (preg_match(self::PATTERN_CIF, $value) === 1) {
            return TaxIdType::CIF;
        }

        $prefix = substr($value, 0, 2);

        if (preg_match('/^[A-Z]{2}/', $value) === 1 && array_key_exists($prefix, self::EU_VAT_PATTERNS)) {
            return TaxIdType::VAT_EU;
        }

        // Sin formato reconocible: se marca como español para que la validación
        // lo rechace y el usuario vea el error, en vez de darlo por bueno.
        return TaxIdType::NIF;
    }

    private static function detectCountry(string $value, TaxIdType $type): string
    {
        if ($type === TaxIdType::VAT_EU) {
            return substr($value, 0, 2);
        }

        return $type->isSpanish() ? self::COUNTRY_SPAIN : '';
    }

    private static function isValidNif(string $value): bool
    {
        if (preg_match(self::PATTERN_NIF, $value) !== 1) {
            return false;
        }

        return self::controlLetterFor(substr($value, 0, 8)) === substr($value, -1);
    }

    private static function isValidNie(string $value): bool
    {
        if (preg_match(self::PATTERN_NIE, $value) !== 1) {
            return false;
        }

        $digits = self::NIE_PREFIXES[$value[0]].substr($value, 1, 7);

        return self::controlLetterFor($digits) === substr($value, -1);
    }

    private static function isValidCif(string $value): bool
    {
        if (preg_match(self::PATTERN_CIF, $value) !== 1) {
            return false;
        }

        $organization = $value[0];
        $digits = substr($value, 1, 7);
        $control = substr($value, -1);

        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $number = (int) $digit;

            // Las posiciones impares (1.ª, 3.ª, 5.ª, 7.ª) se duplican y se suman sus cifras.
            if ($position % 2 === 0) {
                $doubled = $number * 2;
                $number = intdiv($doubled, 10) + ($doubled % 10);
            }

            $sum += $number;
        }

        $expectedDigit = (self::CIF_MODULUS - ($sum % self::CIF_MODULUS)) % self::CIF_MODULUS;
        $expectedLetter = self::CIF_CONTROL_LETTERS[$expectedDigit];

        if (str_contains(self::CIF_LETTER_ONLY, $organization)) {
            return $control === $expectedLetter;
        }

        if (str_contains(self::CIF_DIGIT_ONLY, $organization)) {
            return $control === (string) $expectedDigit;
        }

        return $control === (string) $expectedDigit || $control === $expectedLetter;
    }

    private static function isValidEuVat(string $value): bool
    {
        $country = substr($value, 0, 2);
        $number = substr($value, 2);
        $pattern = self::EU_VAT_PATTERNS[$country] ?? null;

        if ($pattern === null || preg_match($pattern, $number) !== 1) {
            return false;
        }

        // El número español debe superar además su propio control.
        if ($country === self::COUNTRY_SPAIN) {
            return self::of($number)->isValid();
        }

        return true;
    }

    private static function controlLetterFor(string $digits): string
    {
        return self::NIF_CONTROL_LETTERS[((int) $digits) % self::NIF_MODULUS];
    }
}
