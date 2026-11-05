<?php

declare(strict_types=1);

namespace App\Application\Documents\Data;

/** Lo que el usuario decide al enviar un documento por email. */
final readonly class SendData
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function __construct(
        public array $to,
        public array $cc,
        public string $subject,
        public string $body,
    ) {}
}
