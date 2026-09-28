<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use DomainException;

/**
 * Una factura emitida no se modifica ni se borra: se corrige con una
 * rectificativa (decisión D6). Un presupuesto convertido en factura tampoco se
 * modifica.
 */
final class DocumentIsImmutable extends DomainException
{
    /** @param list<string> $attributes */
    public static function cannotChange(string $document, array $attributes): self
    {
        return new self(sprintf(
            'El documento %s ya está emitido y no se puede modificar (%s). Para corregirlo, crea una rectificativa.',
            $document,
            implode(', ', $attributes)
        ));
    }

    public static function cannotDelete(string $document): self
    {
        return new self(sprintf(
            'El documento %s ya está emitido y no se puede borrar. Para anularlo, crea una rectificativa.',
            $document
        ));
    }

    public static function cannotChangeLines(string $document): self
    {
        return new self(sprintf(
            'El documento %s ya está emitido: sus líneas e impuestos no se pueden modificar.',
            $document
        ));
    }

    public static function quoteConverted(string $document): self
    {
        return new self(sprintf(
            'El presupuesto %s ya se convirtió en factura y solo se puede consultar.',
            $document
        ));
    }

    /** El documento ya no admite edición: emitido, o presupuesto convertido. */
    public static function notEditable(Document $document): self
    {
        $label = $document->full_number ?? $document->id;

        return $document->type === DocumentType::Quote
            ? self::quoteConverted($label)
            : self::alreadyIssued($label);
    }

    public static function alreadyIssued(string $document): self
    {
        return new self(sprintf('El documento %s ya está emitido.', $document));
    }

    public static function invalidTransition(string $document, string $from, string $to): self
    {
        return new self(sprintf(
            'El documento %s no puede pasar de "%s" a "%s".',
            $document,
            $from,
            $to
        ));
    }
}
