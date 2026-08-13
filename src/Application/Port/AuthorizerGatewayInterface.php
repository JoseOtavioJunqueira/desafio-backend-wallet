<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * The external service that must approve every transfer before money moves
 * (GET https://util.devi.tools/api/v2/authorize in production).
 */
interface AuthorizerGatewayInterface
{
    /**
     * @return bool true when the transfer is authorized. Implementations must return false
     *              — never throw — for both an explicit denial and an unreachable/erroring
     *              service, so callers can apply a single fail-closed rule.
     */
    public function authorize(): bool;
}
