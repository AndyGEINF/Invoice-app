<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Domain\Shared\TaxId;

/**
 * Genera NIF y CIF españoles con su control correcto, para datos de prueba.
 *
 * Replica el algoritmo de {@see TaxId} a propósito: si ambos
 * divergieran, los tests que usan estas factories lo detectarían.
 */
final class SpanishTaxIds
{
    private const string NIF_CONTROL_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const int NIF_MODULUS = 23;

    private const int NIF_DIGITS = 8;

    private const int CIF_DIGITS = 7;

    private const int CIF_MODULUS = 10;

    /** Sociedades limitadas y anónimas: control numérico. */
    private const array CIF_ORGANIZATIONS = ['A', 'B'];

    public static function nif(): string
    {
        $digits = self::randomDigits(self::NIF_DIGITS);

        return $digits.self::NIF_CONTROL_LETTERS[((int) $digits) % self::NIF_MODULUS];
    }

    public static function cif(): string
    {
        $organization = self::CIF_ORGANIZATIONS[array_rand(self::CIF_ORGANIZATIONS)];
        $digits = self::randomDigits(self::CIF_DIGITS);

        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $number = (int) $digit;

            if ($position % 2 === 0) {
                $doubled = $number * 2;
                $number = intdiv($doubled, 10) + ($doubled % 10);
            }

            $sum += $number;
        }

        $control = (self::CIF_MODULUS - ($sum % self::CIF_MODULUS)) % self::CIF_MODULUS;

        return $organization.$digits.$control;
    }

    private static function randomDigits(int $length): string
    {
        $digits = '';

        for ($i = 0; $i < $length; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return $digits;
    }
}
