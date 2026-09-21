<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use InvalidArgumentException;
use Stringable;

/**
 * Número completo de un documento: F2026-0001.
 *
 * Se compone del prefijo de la serie, el año cuando la serie se reinicia cada
 * ejercicio, y el número correlativo rellenado con ceros. El número lo asigna
 * la emisión, nunca la creación del borrador (decisión D5).
 */
final readonly class DocumentNumber implements Stringable
{
    /** Ceros con los que se rellena el número si la serie no dice otra cosa. */
    public const int DEFAULT_PADDING = 4;

    public const int MIN_PADDING = 1;

    public const int MAX_PADDING = 8;

    /** Separador entre el prefijo (con año o sin él) y el número. */
    public const string SEPARATOR = '-';

    public const int FIRST_NUMBER = 1;

    private function __construct(
        public string $prefix,
        public ?int $year,
        public int $number,
        public int $padding,
    ) {}

    public static function of(
        string $prefix,
        int $number,
        int $padding = self::DEFAULT_PADDING,
        ?int $year = null,
    ): self {
        if ($number < self::FIRST_NUMBER) {
            throw new InvalidArgumentException(
                sprintf('El número de documento empieza en %d, recibido %d.', self::FIRST_NUMBER, $number)
            );
        }

        if ($padding < self::MIN_PADDING || $padding > self::MAX_PADDING) {
            throw new InvalidArgumentException(
                sprintf('El relleno debe estar entre %d y %d, recibido %d.', self::MIN_PADDING, self::MAX_PADDING, $padding)
            );
        }

        return new self(strtoupper(trim($prefix)), $year, $number, $padding);
    }

    /** Número completo tal como aparece en la factura: F2026-0001 */
    public function full(): string
    {
        return $this->prefix
            .($this->year !== null ? (string) $this->year : '')
            .self::SEPARATOR
            .$this->padded();
    }

    /** Solo el correlativo con ceros: 0001 */
    public function padded(): string
    {
        return str_pad((string) $this->number, $this->padding, '0', STR_PAD_LEFT);
    }

    public function next(): self
    {
        return new self($this->prefix, $this->year, $this->number + 1, $this->padding);
    }

    public function equals(self $other): bool
    {
        return $this->full() === $other->full();
    }

    public function __toString(): string
    {
        return $this->full();
    }
}
