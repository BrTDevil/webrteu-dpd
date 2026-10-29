<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Requests;

use WeBRTeu\Dpd\Models\DpdSite;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * ShipmentData → corpul shipment/. ⚠️ Structura `additionalServices` se confirmă pe services.dpd.ro/api.
 */
final readonly class ShipmentPayload
{
    public const COUNTRY_RO = 642;

    public function __construct(
        private ?int $senderClientId,
        private ?int $serviceId,
        private string $codProcessing = 'CASH',
        private string $payer = 'SENDER',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function make(ShipmentData $shipment): array
    {
        if ($shipment->sender instanceof AddressData) {
            throw UnsupportedOperationException::for('dpd', 'sender (expeditorul vine din DPD_SENDER_CLIENT_ID)');
        }

        $payload = [
            'sender' => $this->sender(),
            'recipient' => $this->recipient($shipment),
            'service' => $this->service($shipment),
            'content' => $this->content($shipment),
            'payment' => ['courierServicePayer' => $this->payer],
            'ref1' => $shipment->reference,
        ];

        if ($shipment->observations !== null && $shipment->observations !== '') {
            $payload['shipmentNote'] = $shipment->observations;
        }

        return $payload;
    }

    /**
     * Corpul pentru calculate/: aceleași date, fără adresa completă.
     *
     * @return array<string, mixed>
     */
    public function calculation(ShipmentData $shipment): array
    {
        $payload = $this->make($shipment);
        $service = $payload['service'];
        $service['serviceIds'] = [$service['serviceId']];
        unset($service['serviceId']);

        $recipient = $payload['recipient'];
        $location = isset($recipient['pickupOfficeId'])
            ? ['pickupOfficeId' => $recipient['pickupOfficeId']]
            : ['addressLocation' => array_intersect_key($recipient['address'], array_flip(['countryId', 'siteId', 'siteName', 'postCode']))];

        return [
            'sender' => $payload['sender'],
            'recipient' => ['privatePerson' => $recipient['privatePerson']] + $location,
            'service' => $service,
            'content' => $payload['content'],
            'payment' => $payload['payment'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sender(): array
    {
        if ($this->senderClientId === null) {
            throw new ShipmentValidationException('Lipsește DPD_SENDER_CLIENT_ID (vezi php artisan dpd:contract).');
        }

        return ['clientId' => $this->senderClientId];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipient(ShipmentData $shipment): array
    {
        $address = $shipment->recipient;
        $recipient = [
            'phone1' => ['number' => Normalizer::phone($address->phone)],
            'clientName' => $address->company ?? $address->name,
            'contactName' => $address->contactPerson ?? $address->name,
            'email' => (string) $address->email,
            'privatePerson' => $address->company === null,
        ];

        if ($shipment->isPickupPointDelivery()) {
            return $recipient + ['pickupOfficeId' => (int) $shipment->pickupPointId];
        }

        $site = DpdSite::query()
            ->where('region_key', Normalizer::key($address->county))
            ->where('name_key', Normalizer::key($address->city))
            ->first();

        return $recipient + ['address' => array_filter([
            'countryId' => self::COUNTRY_RO,
            'siteId' => $site instanceof DpdSite ? $site->site_id : null,
            'siteName' => $site instanceof DpdSite ? null : Normalizer::ascii($address->city),
            'postCode' => $address->postalCode ?? ($site instanceof DpdSite ? $site->post_code : null),
            'streetName' => $address->street,
            'streetNo' => $address->number,
            'blockNo' => $address->block,
            'entranceNo' => $address->staircase,
            'floorNo' => $address->floor,
            'apartmentNo' => $address->apartment,
        ], fn (mixed $value): bool => $value !== null && $value !== '')];
    }

    /**
     * @return array<string, mixed>
     */
    private function service(ShipmentData $shipment): array
    {
        $serviceId = $shipment->serviceCode !== null && $shipment->serviceCode !== '' ? (int) $shipment->serviceCode : $this->serviceId;

        if ($serviceId === null) {
            throw new ShipmentValidationException('Lipsește DPD_SERVICE_ID.');
        }

        $additional = [];

        if ($shipment->hasCashOnDelivery()) {
            $additional['cod'] = ['amount' => Money::toFloat($shipment->codAmount), 'processingType' => $this->codProcessing];
        }

        if ($shipment->declaredValue > 0) {
            $additional['declaredValue'] = ['amount' => Money::toFloat($shipment->declaredValue)];
        }

        if ($shipment->openPackage) {
            $additional['obpd'] = ['option' => 'OPEN', 'returnShipmentServiceId' => $serviceId, 'returnShipmentPayer' => $this->payer];
        }

        return array_filter([
            'serviceId' => $serviceId,
            'autoAdjustPickupDate' => true,
            'saturdayDelivery' => $shipment->saturdayDelivery,
            'additionalServices' => $additional,
        ], fn (mixed $value): bool => $value !== [] && $value !== false);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(ShipmentData $shipment): array
    {
        $parcels = array_map(fn (ParcelData $parcel, int $index): array => array_filter([
            'seqNo' => $index + 1,
            'weight' => $parcel->weightKg,
            'size' => $parcel->lengthCm !== null && $parcel->widthCm !== null && $parcel->heightCm !== null
                ? ['depth' => $parcel->lengthCm, 'width' => $parcel->widthCm, 'height' => $parcel->heightCm]
                : null,
        ], fn (mixed $value): bool => $value !== null), $shipment->parcels, array_keys($shipment->parcels));

        return [
            'parcelsCount' => max(1, $shipment->parcelCount()),
            'totalWeight' => max($shipment->totalWeightKg(), 0.1),
            'contents' => $shipment->contents !== '' ? $shipment->contents : 'Colet',
            'package' => $shipment->parcels === [] ? 'ENVELOPE' : 'BOX',
            'documents' => $shipment->parcels === [],
            'parcels' => $parcels,
        ];
    }
}
