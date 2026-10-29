<?php

declare(strict_types=1);

use WeBRTeu\Dpd\DpdStatusMap;
use WeBRTeu\Shipping\Enums\ShipmentStatus;

it('maps DPD operations', function (string $code, string $label, ShipmentStatus $expected): void {
    expect(DpdStatusMap::resolve($code, $label))->toBe($expected);
})->with([
    ['-14', '', ShipmentStatus::Delivered],
    ['1', 'Preluat de curier', ShipmentStatus::PickedUp],
    ['4', 'În tranzit', ShipmentStatus::InTransit],
    ['44', 'Depus în DPD Box (locker)', ShipmentStatus::AtPickupPoint],
]);
