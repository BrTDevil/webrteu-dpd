<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Dpd\Exceptions\DpdException;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('returns the shipment id and the parcel ids', function (): void {
    Http::fake([DPD.'shipment/' => Http::response(fixtureFile('shipment-created.json'))]);

    $awb = Shipping::carrier('dpd')->createShipment(shipmentData());

    expect($awb->awb)->toBe('80123456789')
        ->and($awb->parcelNumbers)->toBe(['80123456789', '80123456790'])
        ->and($awb->cost)->toBe(1892)
        ->and($awb->raw['parcels'])->toBe([['id' => '80123456789'], ['id' => '80123456790']]);
});

it('uses the shipment id when no parcels come back', function (): void {
    Http::fake([DPD.'shipment/' => Http::response(['id' => '5'])]);

    expect(Shipping::carrier('dpd')->createShipment(shipmentData()))
        ->parcelNumbers->toBe(['5'])
        ->cost->toBeNull();
});

it('turns a body error into a validation error', function (): void {
    Http::fake([DPD.'shipment/' => Http::response(fixtureFile('shipment-error-200.json'))]);

    Shipping::carrier('dpd')->createShipment(shipmentData());
})->throws(ShipmentValidationException::class, 'Localitatea');

it('throws ShipmentStateUnknownException on timeout, without retry', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    });

    expect(fn () => Shipping::carrier('dpd')->createShipment(shipmentData()))->toThrow(ShipmentStateUnknownException::class);
    expect($attempts)->toBe(1);
});

it('fails without a shipment id', function (): void {
    Http::fake([DPD.'shipment/' => Http::response([])]);

    Shipping::carrier('dpd')->createShipment(shipmentData());
})->throws(DpdException::class, 'id-ul expediției');

it('quotes, lists services and cancels', function (): void {
    Http::fake([
        DPD.'calculate/' => Http::response(fixtureFile('calculate.json')),
        DPD.'services/' => Http::response(fixtureFile('services.json')),
        DPD.'shipment/cancel/' => Http::response([]),
    ]);

    expect(Shipping::carrier('dpd')->quote(shipmentData()))->amount->toBe(1892)->serviceCode->toBe('2505')->currency->toBe('RON')
        ->and(Shipping::carrier('dpd')->services()->pluck('name')->all())->toBe(['DPD STANDARD', 'PALLET ONE RO'])
        ->and(Shipping::carrier('dpd')->cancel('80123456789'))->toBeTrue();
});

it('quotes an empty calculation as zero', function (): void {
    Http::fake([DPD.'calculate/' => Http::response(['calculations' => [['price' => []]]])]);

    expect(Shipping::carrier('dpd')->quote(shipmentData()))->amount->toBe(0)->serviceCode->toBeNull();
});
