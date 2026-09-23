<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

/**
 * PDF generado de un documento, con la huella de su contenido.
 *
 * La huella permite comprobar años después que el PDF de una factura emitida
 * sigue siendo el mismo que se generó al emitirla.
 */
final readonly class RenderedPdf
{
    private const string HASH_ALGORITHM = 'sha256';

    private function __construct(
        public string $bytes,
        public string $sha256,
    ) {}

    public static function fromBytes(string $bytes): self
    {
        return new self($bytes, hash(self::HASH_ALGORITHM, $bytes));
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
