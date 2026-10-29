<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Dpd\Facades\Dpd;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Facades\Shipping;

it('tracks several parcels and skips the ones with errors', function (): void {
    Http::fake([DPD.'track/' => Http::response(fixtureFile('track.json'))]);

    $results = Shipping::carrier('dpd')->trackMany(['80123456789', '80123456791', '999']);

    expect($results->keys()->map(strval(...))->all())->toBe(['80123456789', '80123456791'])
        ->and($results['80123456789']->status)->toBe(ShipmentStatus::Delivered)
        ->and($results['80123456789']->events[0]->location)->toBe('Brasov')
        ->and($results['80123456791']->events[0]->occurredAt)->toBeNull()
        ->and(Shipping::carrier('dpd')->track('999')->status)->toBe(ShipmentStatus::Unknown);

    Http::assertSent(fn (Request $request): bool => $request['parcels'] === [['id' => '80123456789'], ['id' => '80123456791'], ['id' => '999']]);
});

it('orders a pickup with or without a contact', function (): void {
    Http::fake([DPD.'pickup/' => Http::sequence()->push(['orders' => [['id' => 4321]]])->push([])]);

    $contact = new AddressData('Ana', '+40 720 000 000', 'Brașov', 'Brașov', 'Str. X', '1');

    expect(Dpd::orderPickup(new PickupRequestData(CarbonImmutable::parse('2026-01-05', 'Europe/Bucharest'), '10:00', '16:00', 2, 3.0, $contact)))->toBe('4321')
        ->and(Dpd::orderPickup(new PickupRequestData(CarbonImmutable::parse('2026-01-05'), '10:00', '16:00', 2, 3.0)))->toBe('');

    Http::assertSent(fn (Request $request): bool => ($request['phoneNumber'] ?? null) === ['number' => '0720000000'] && $request['visitEndTime'] === '16:00');
});
