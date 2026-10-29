<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd;

use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Support\StatusResolver;

/**
 * `operationCode` din track/ → ShipmentStatus.
 * ⚠️ Doar codurile documentate public (-14 livrat); restul se deduce din descriere și se completează din trasee reale.
 */
final class DpdStatusMap
{
    /**
     * @var array<array-key, ShipmentStatus>
     */
    public const MAP = [
        '-14' => ShipmentStatus::Delivered,
    ];

    public static function resolve(string $code, string $label): ShipmentStatus
    {
        return StatusResolver::resolve('dpd', $code, $label, self::MAP);
    }
}
