<?php

declare(strict_types=1);

use WeBRTeu\Dpd\Models\DpdSite;
use WeBRTeu\Dpd\Requests\ShipmentPayload;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;

function dpdPayload(array $overrides = [], ?int $sender = 12345678901, ?int $service = 2505): array
{
    return (new ShipmentPayload($sender, $service))->make(shipmentData($overrides));
}

it('addresses Romania by countryId 642 and the synced siteId', function (): void {
    DpdSite::query()->create(['site_id' => 642279133, 'name' => 'RASNOV', 'region' => 'BRASOV', 'post_code' => '505400', 'name_key' => 'rasnov', 'region_key' => 'brasov']);

    $address = dpdPayload()['recipient']['address'];

    expect($address)->toMatchArray(['countryId' => 642, 'siteId' => 642279133, 'postCode' => '505400', 'streetName' => 'Str. Republicii', 'streetNo' => '10', 'blockNo' => 'A', 'apartmentNo' => '3'])
        ->not->toHaveKey('siteName');
});

it('falls back to the site name without diacritics', function (): void {
    expect(dpdPayload()['recipient']['address'])->toMatchArray(['siteName' => 'Rasnov'])->not->toHaveKeys(['siteId', 'postCode', 'floorNo']);
});

it('adds cash on delivery, declared value, open package and several parcels', function (): void {
    $payload = dpdPayload(['openPackage' => true, 'saturdayDelivery' => true, 'observations' => 'Sunați']);

    expect($payload['service'])->toBe([
        'serviceId' => 2505,
        'autoAdjustPickupDate' => true,
        'saturdayDelivery' => true,
        'additionalServices' => [
            'cod' => ['amount' => 149.7, 'processingType' => 'CASH'],
            'declaredValue' => ['amount' => 200.0],
            'obpd' => ['option' => 'OPEN', 'returnShipmentServiceId' => 2505, 'returnShipmentPayer' => 'SENDER'],
        ],
    ])->and($payload['content'])->toMatchArray(['parcelsCount' => 2, 'totalWeight' => 2.0, 'package' => 'BOX', 'contents' => 'Colet'])
        ->and($payload['content']['parcels'])->toBe([
            ['seqNo' => 1, 'weight' => 1.2, 'size' => ['depth' => 30, 'width' => 20, 'height' => 10]],
            ['seqNo' => 2, 'weight' => 0.8],
        ])
        ->and($payload['ref1'])->toBe('CMD-1024')
        ->and($payload['shipmentNote'])->toBe('Sunați')
        ->and($payload['recipient'])->toMatchArray(['clientName' => 'Ion Popescu', 'privatePerson' => true, 'phone1' => ['number' => '0721000000']]);
});

it('delivers to a DPD Box by officeId', function (): void {
    $recipient = dpdPayload(['pickupPointId' => '901', 'codAmount' => 0, 'declaredValue' => 0])['recipient'];

    expect($recipient['pickupOfficeId'])->toBe(901)
        ->and($recipient)->not->toHaveKey('address')
        ->and(dpdPayload(['codAmount' => 0, 'declaredValue' => 0])['service'])->not->toHaveKey('additionalServices');
});

it('sends envelopes and companies', function (): void {
    $company = new AddressData('Ion', '0721000000', 'Cluj', 'Cluj', 'Str. X', '1', company: 'Exemplu SRL');
    $payload = dpdPayload(['parcels' => [], 'envelopes' => 1, 'recipient' => $company, 'contents' => 'Acte', 'serviceCode' => '2002']);

    expect($payload['content'])->toMatchArray(['package' => 'ENVELOPE', 'documents' => true, 'parcelsCount' => 1, 'contents' => 'Acte'])
        ->and($payload['recipient'])->toMatchArray(['clientName' => 'Exemplu SRL', 'contactName' => 'Ion', 'privatePerson' => false])
        ->and($payload['service']['serviceId'])->toBe(2002);
});

it('builds the calculation body', function (bool $office): void {
    $body = (new ShipmentPayload(1, 2505))->calculation(shipmentData($office ? ['pickupPointId' => '901'] : []));

    expect($body['service']['serviceIds'])->toBe([2505])
        ->and($body['service'])->not->toHaveKey('serviceId')
        ->and($body['recipient'])->toHaveKey($office ? 'pickupOfficeId' : 'addressLocation');
})->with([true, false]);

it('requires the sender and the service', function (): void {
    expect(fn () => dpdPayload(sender: null))->toThrow(ShipmentValidationException::class, 'DPD_SENDER_CLIENT_ID')
        ->and(fn () => dpdPayload(service: null))->toThrow(ShipmentValidationException::class, 'DPD_SERVICE_ID')
        ->and(fn () => dpdPayload(['sender' => new AddressData('F', '0700000000', 'Cluj', 'Cluj', 'X', '1')]))->toThrow(UnsupportedOperationException::class);
});
