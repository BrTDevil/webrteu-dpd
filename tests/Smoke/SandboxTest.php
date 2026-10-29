<?php

declare(strict_types=1);

use WeBRTeu\Shipping\Facades\Shipping;
use WeBRTeu\Shipping\Models\Shipment;

it('creates, prints and cancels a shipment', function (): void {
    $data = shipmentData(['reference' => 'SMOKE-'.time()]);
    $awb = Shipping::carrier('dpd')->createShipment($data);
    Shipment::recordFrom($awb, $data);

    expect(Shipping::carrier('dpd')->label($awb->awb))->toStartWith('%PDF')
        ->and(Shipping::carrier('dpd')->cancel($awb->awb))->toBeTrue();
})->group('sandbox')->skip(! env('DPD_SANDBOX_USERNAME'), 'Setează DPD_SANDBOX_USERNAME / _PASSWORD / _SENDER_CLIENT_ID / _SERVICE_ID.');
