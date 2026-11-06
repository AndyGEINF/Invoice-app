<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Enums;

/** Resultado del correo de prueba que se manda al guardar la cuenta de correo. */
enum MailTestStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Failed = 'failed';
}
