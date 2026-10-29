<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Facades\Shipping;
use WeBRTeu\Shipping\Models\Shipment;

it('prints every stored parcel and returns the binary PDF', function (LabelFormat $format, string $paper, string $type): void {
    Shipment::query()->create(['carrier' => 'dpd', 'awb' => '80123456789', 'status' => ShipmentStatus::Created, 'raw' => ['parcels' => [['id' => '80123456789'], ['id' => '80123456790'], 'x']]]);
    Http::fake([DPD.'print/' => Http::response(fixtureFile('print.pdf'), 200, ['Content-Type' => 'application/pdf'])]);

    expect(Shipping::carrier('dpd')->label('80123456789', $format))->toStartWith('%PDF');

    Http::assertSent(fn (Request $request): bool => $request['paperSize'] === $paper
        && $request['format'] === $type
        && $request['parcels'] === [['parcel' => ['id' => '80123456789']], ['parcel' => ['id' => '80123456790']]]);
})->with([
    [LabelFormat::A6Pdf, 'A6', 'pdf'],
    [LabelFormat::A4Pdf, 'A4', 'pdf'],
    [LabelFormat::Zpl, 'A6', 'zpl'],
]);

it('prints the shipment id when the shipment is not stored', function (): void {
    Http::fake([DPD.'print/' => Http::response(fixtureFile('print.pdf'), 200, ['Content-Type' => 'application/pdf'])]);

    Shipping::carrier('dpd')->label('80123456789');

    Http::assertSent(fn (Request $request): bool => $request['parcels'] === [['parcel' => ['id' => '80123456789']]]);
});
