<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Facades;

use Illuminate\Support\Facades\Facade;
use WeBRTeu\Dpd\DpdCarrier;

/**
 * @method static \WeBRTeu\Shipping\Data\AwbData createShipment(\WeBRTeu\Shipping\Data\ShipmentData $shipment)
 * @method static \WeBRTeu\Shipping\Data\TrackingData track(string $awb)
 * @method static string orderPickup(\WeBRTeu\Shipping\Data\PickupRequestData $request)
 * @method static \WeBRTeu\Dpd\DpdClient client()
 *
 * @see DpdCarrier
 */
class Dpd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DpdCarrier::class;
    }
}
