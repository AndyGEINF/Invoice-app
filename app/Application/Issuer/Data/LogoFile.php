<?php

declare(strict_types=1);

namespace App\Application\Issuer\Data;

/**
 * Logotipo subido: dónde está el fichero temporal y con qué extensión se guarda.
 * Independiente de HTTP para que el caso de uso no conozca la petición.
 */
final readonly class LogoFile
{
    public function __construct(
        public string $path,
        public string $extension,
    ) {}
}
