<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Exceptions;

use WeBRTeu\Shipping\Exceptions\ShippingException;

class DpdException extends ShippingException
{
    /**
     * @param  array<array-key, mixed>  $error
     */
    public static function fromError(string $path, array $error): self
    {
        $message = is_scalar($error['message'] ?? null) ? (string) $error['message'] : 'eroare necunoscută';
        $code = is_numeric($error['code'] ?? null) ? (int) $error['code'] : 0;

        return new self(sprintf('DPD %s: %s', $path, $message), $code);
    }
}
