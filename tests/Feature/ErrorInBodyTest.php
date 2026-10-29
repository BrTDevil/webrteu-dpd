<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use WeBRTeu\Dpd\Exceptions\DpdException;
use WeBRTeu\Shipping\Facades\Shipping;

it('throws for an error in the body with HTTP 200', function (): void {
    Http::fake([DPD.'*' => Http::response(fixtureFile('shipment-error-200.json'))]);

    Shipping::carrier('dpd')->track('1');
})->throws(DpdException::class, 'Localitatea nu a fost găsită');

it('throws for HTTP errors without a JSON body', function (): void {
    Http::fake([DPD.'*' => Http::response('Bad gateway', 502, ['Content-Type' => 'text/html'])]);

    Shipping::carrier('dpd')->track('1');
})->throws(DpdException::class, 'HTTP 502');

it('throws for a calculation error', function (): void {
    Http::fake([DPD.'calculate/' => Http::response(['calculations' => [['serviceId' => 2505, 'error' => ['message' => 'Serviciu indisponibil']]]])]);

    Shipping::carrier('dpd')->quote(shipmentData());
})->throws(DpdException::class, 'Serviciu indisponibil');
