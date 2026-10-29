<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use WeBRTeu\Dpd\Exceptions\DpdException;
use WeBRTeu\Dpd\Models\DpdOffice;
use WeBRTeu\Dpd\Requests\ShipmentPayload;
use WeBRTeu\Shipping\Contracts\Carrier;
use WeBRTeu\Shipping\Contracts\HasBulkTracking;
use WeBRTeu\Shipping\Contracts\HasCourierOrder;
use WeBRTeu\Shipping\Contracts\HasPickupPoints;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\AwbData;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Data\QuoteData;
use WeBRTeu\Shipping\Data\ServiceData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Data\TrackingData;
use WeBRTeu\Shipping\Data\TrackingEventData;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Models\Shipment;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * AWB-ul = id-ul expediției DPD. Eticheta se tipărește per colet: id-urile coletelor stau în AwbData::raw['parcels'].
 */
class DpdCarrier implements Carrier, HasBulkTracking, HasCourierOrder, HasPickupPoints
{
    public function __construct(private readonly DpdClient $client) {}

    public function name(): string
    {
        return 'dpd';
    }

    public function client(): DpdClient
    {
        return $this->client;
    }

    public function services(): Collection
    {
        $json = $this->client->json('services/', ['date' => CarbonImmutable::now()->toDateString()]);

        return collect($this->list($json['services'] ?? null))->map(fn (array $service): ServiceData => new ServiceData(
            code: self::string($service, 'id'),
            name: self::string($service, 'name') !== '' ? self::string($service, 'name') : self::string($service, 'nameEn'),
            pickupPoint: false,
            raw: $service,
        ))->values();
    }

    public function quote(ShipmentData $shipment): QuoteData
    {
        $json = $this->client->json('calculate/', $this->payload()->calculation($shipment));
        $calculation = $this->list($json['calculations'] ?? null)[0] ?? [];

        if (is_array($calculation['error'] ?? null)) {
            throw DpdException::fromError('calculate/', $calculation['error']);
        }

        $price = is_array($calculation['price'] ?? null) ? $calculation['price'] : [];

        return new QuoteData(
            carrier: $this->name(),
            amount: Money::fromDecimal($this->decimal($price['total'] ?? $price['amount'] ?? 0)),
            currency: self::string($price, 'currency') !== '' ? self::string($price, 'currency') : 'RON',
            serviceCode: self::string($calculation, 'serviceId') !== '' ? self::string($calculation, 'serviceId') : null,
            raw: $json,
        );
    }

    public function createShipment(ShipmentData $shipment): AwbData
    {
        $payload = $this->payload()->make($shipment);

        try {
            $json = $this->client->json('shipment/', $payload);
        } catch (ConnectionException $exception) {
            throw new ShipmentStateUnknownException($this->name(), $shipment->reference, $exception);
        } catch (DpdException $exception) {
            throw ShipmentValidationException::withErrors($this->name(), ['general' => [$exception->getMessage()]], $exception);
        }

        $id = self::string($json, 'id');

        if ($id === '') {
            throw new DpdException('DPD nu a întors id-ul expediției.');
        }

        $parcels = array_values(array_filter(array_map(fn (array $parcel): string => self::string($parcel, 'id'), $this->list($json['parcels'] ?? null))));
        $price = is_array($json['price'] ?? null) ? $json['price'] : [];

        return new AwbData(
            awb: $id,
            carrier: $this->name(),
            cost: isset($price['total']) ? Money::fromDecimal($this->decimal($price['total'])) : null,
            parcelNumbers: $parcels !== [] ? $parcels : [$id],
            raw: ['parcels' => array_map(fn (string $parcel): array => ['id' => $parcel], $parcels !== [] ? $parcels : [$id])] + $json,
        );
    }

    public function label(string $awb, LabelFormat $format = LabelFormat::A6Pdf): string
    {
        return $this->client->send('print/', [
            'paperSize' => $format === LabelFormat::A4Pdf ? 'A4' : 'A6',
            'format' => $format === LabelFormat::Zpl ? 'zpl' : 'pdf',
            'parcels' => array_map(fn (string $id): array => ['parcel' => ['id' => $id]], $this->parcelIds($awb)),
        ])->body();
    }

    public function track(string $awb): TrackingData
    {
        return $this->trackMany([$awb])->get($awb) ?? new TrackingData($awb, ShipmentStatus::Unknown, 'Expediție negăsită la DPD');
    }

    public function trackMany(array $awbs): Collection
    {
        $json = $this->client->json('track/', [
            'parcels' => array_map(fn (string $awb): array => ['id' => $awb], $awbs),
            'lastOperationOnly' => false,
        ]);

        /** @var Collection<string, TrackingData> $results */
        $results = collect();

        foreach ($this->list($json['parcels'] ?? null) as $parcel) {
            $id = self::string($parcel, 'parcelId');

            if ($id === '' || is_array($parcel['error'] ?? null)) {
                continue;
            }

            $events = array_map($this->event(...), $this->list($parcel['operations'] ?? null));
            $results->put($id, TrackingData::fromEvents($id, $events, $parcel));
        }

        return $results;
    }

    public function cancel(string $awb): bool
    {
        $this->client->json('shipment/cancel/', ['shipmentId' => $awb, 'comment' => 'Anulat din aplicație']);

        return true;
    }

    /**
     * Oficii + DPD Box: din tabela dpd_offices sau direct din location/office/.
     */
    public function pickupPoints(?string $county = null, ?string $city = null): Collection
    {
        $points = DpdOffice::query()->where('active', true)->exists()
            ? DpdOffice::query()->where('active', true)->get()->map(fn (DpdOffice $office): PickupPointData => self::pickupPoint($office->raw ?? []))
            : $this->remotePickupPoints();

        return $points
            ->filter(fn (PickupPointData $point): bool => ($county === null || Normalizer::same($point->county, $county))
                && ($city === null || Normalizer::same($point->city, $city)))
            ->values();
    }

    /**
     * @return Collection<int, PickupPointData>
     */
    public function remotePickupPoints(): Collection
    {
        return collect($this->list($this->client->json('location/office/', ['countryId' => ShipmentPayload::COUNTRY_RO])['offices'] ?? null))
            ->map(fn (array $office): PickupPointData => self::pickupPoint($office))
            ->values();
    }

    /**
     * ⚠️ Structura exactă a cererii pickup/ se confirmă pe services.dpd.ro/api.
     */
    public function orderPickup(PickupRequestData $request): string
    {
        $body = [
            'pickupDateTime' => $request->date->setTimeFromTimeString($request->fromHour)->format('Y-m-d\TH:i:sP'),
            'pickupScope' => 'ALL_CLIENT_SHIPMENTS',
            'visitEndTime' => $request->toHour,
            'autoAdjustPickupDate' => true,
        ];

        if ($request->address instanceof AddressData) {
            $body['contactName'] = $request->address->contactPerson ?? $request->address->name;
            $body['phoneNumber'] = ['number' => Normalizer::phone($request->address->phone)];
        }

        $json = $this->client->json('pickup/', $body);

        $order = $this->list($json['orders'] ?? null)[0] ?? [];

        return self::string($order, 'id');
    }

    private function payload(): ShipmentPayload
    {
        return new ShipmentPayload(
            senderClientId: is_numeric($sender = Config::get('dpd.sender_client_id')) ? (int) $sender : null,
            serviceId: is_numeric($service = Config::get('dpd.default_service_id')) ? (int) $service : null,
            codProcessing: Config::string('dpd.cod_processing', 'CASH'),
            payer: Config::string('dpd.payer', 'SENDER'),
        );
    }

    /**
     * @return list<string>
     */
    private function parcelIds(string $awb): array
    {
        $shipment = Shipment::query()->where('carrier', $this->name())->where('awb', $awb)->first();
        $parcels = $shipment instanceof Shipment ? ($shipment->raw['parcels'] ?? null) : null;
        $ids = is_array($parcels) ? array_values(array_filter(array_map(fn (mixed $parcel): string => is_array($parcel) ? self::string($parcel, 'id') : '', $parcels))) : [];

        // Fără expediția salvată: la DPD primul colet are, de regulă, același id ca expediția.
        return $ids !== [] ? $ids : [$awb];
    }

    /**
     * @param  array<array-key, mixed>  $office
     */
    public static function pickupPoint(array $office): PickupPointData
    {
        $address = is_array($office['address'] ?? null) ? $office['address'] : [];

        return new PickupPointData(
            id: self::string($office, 'id'),
            carrier: 'dpd',
            type: self::string($office, 'type') === 'APT' ? PickupPointType::Locker : PickupPointType::Office,
            name: self::string($office, 'name'),
            county: self::string($address, 'regionName') !== '' ? self::string($address, 'regionName') : self::string($address, 'stateName'),
            city: self::string($address, 'siteName'),
            address: self::string($address, 'fullAddressString') !== '' ? self::string($address, 'fullAddressString') : self::string($address, 'localAddressString'),
            lat: is_numeric($address['y'] ?? null) ? (float) $address['y'] : null,
            lng: is_numeric($address['x'] ?? null) ? (float) $address['x'] : null,
            postalCode: self::string($address, 'postCode') !== '' ? self::string($address, 'postCode') : null,
            supportsCod: (bool) ($office['cashPaymentAllowed'] ?? false) || (bool) ($office['cardPaymentAllowed'] ?? false),
            raw: $office,
        );
    }

    /**
     * @param  array<array-key, mixed>  $operation
     */
    private function event(array $operation): TrackingEventData
    {
        $code = self::string($operation, 'operationCode');
        $label = self::string($operation, 'description');
        $date = self::string($operation, 'dateTime');

        return new TrackingEventData(
            status: DpdStatusMap::resolve($code, $label),
            code: $code,
            label: $label,
            occurredAt: $date !== '' ? CarbonImmutable::parse($date) : null,
            location: self::string($operation, 'place') !== '' ? self::string($operation, 'place') : null,
            raw: $operation,
        );
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
    }

    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0';
    }
}
