<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum DocumentType: string
{
    case CPF = 'cpf';
    case CNPJ = 'cnpj';
}
