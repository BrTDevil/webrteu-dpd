# webrteu/dpd

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%E2%80%9313-ff2d20)](https://laravel.com/)
[![Licență MIT](https://img.shields.io/badge/licen%C8%9B%C4%83-MIT-green)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/webrteu/dpd)](https://packagist.org/packages/webrteu/dpd)

Integrare Laravel pentru DPD România, API REST v1 (`https://api.dpd.ro/v1/`): expediții, etichete per colet,
tracking multiplu, oficii și DPD Box, comenzi de ridicare. Implementează contractele din `webrteu/shipping`.

Nu este un pachet oficial DPD.

## Cerințe

- PHP `^8.2`, Laravel 12–13
- contract DPD România cu acces API (utilizator + parolă)
- `webrteu/shipping`

## Instalare

```bash
composer require webrteu/dpd
php artisan vendor:publish --tag=dpd-config
php artisan vendor:publish --tag=dpd-migrations
php artisan migrate
php artisan dpd:contract
php artisan dpd:sync-locations
```

## Configurare

```dotenv
DPD_USERNAME=
DPD_PASSWORD=
DPD_LANGUAGE=RO
DPD_CLIENT_SYSTEM_ID=      # opțional, dacă îl primești de la DPD
DPD_SENDER_CLIENT_ID=      # vezi php artisan dpd:contract
DPD_SERVICE_ID=
DPD_COD_PROCESSING=CASH
DPD_PAYER=SENDER
DPD_TIMEOUT=20
```

Nu există token: `userName`/`password`/`language` se adaugă în corpul fiecărei cereri. Corpurile se loghează doar
mascate (`redact()`): parola și utilizatorul nu apar niciodată în loguri.

⚠️ Căile și câmpurile (`additionalServices.cod`, `obpd`, `pickup/`, CSV-ul `location/site/csv/642`) se confirmă pe
`services.dpd.ro/api`.

## Utilizare

```php
$awb = Shipping::carrier('dpd')->createShipment($data);   // AWB = id-ul expediției
Shipment::recordFrom($awb, $data, $order);                 // păstrează id-urile coletelor pentru etichete
$pdf = Shipping::carrier('dpd')->label($awb->awb);         // print/ cu toate coletele
```

| Metodă | DPD |
|---|---|
| `services()` | `services/` |
| `quote($data)` | `calculate/` |
| `createShipment($data)` | `shipment/` → id expediție + `parcels[].id`; eroare → `ShipmentValidationException`; timeout → `ShipmentStateUnknownException` |
| `label($awb, $format)` | `print/` per colet (A4/A6 PDF sau ZPL) |
| `track` / `trackMany` | `track/` |
| `cancel($awb)` | `shipment/cancel/` |
| `pickupPoints()` | oficii + DPD Box (`dpd_offices` sau `location/office/`) |
| `orderPickup($request)` | `pickup/` ⚠️ |

Erorile din câmpul `error` sunt tratate și când vin cu HTTP 200. România = `countryId 642`; localitatea se trimite
prin `siteId` când nomenclatorul e sincronizat, altfel prin `siteName`. DPD Box: `pickupPointId` → `pickupOfficeId`.

## Comenzi Artisan

| Comandă | Ce face |
|---|---|
| `dpd:sync-locations` | localitățile RO în `dpd_sites` |
| `dpd:sync-offices` | oficii + DPD Box în `dpd_offices` |
| `dpd:contract` | `clientId`-urile contului |

## Statusuri

⚠️ `DpdStatusMap` conține doar `-14` (livrat). Restul se deduce din descriere. Fără webhook → `shipping:sync`.

## Ce nu face pachetul

- nu suportă expeditori diferiți de clientul din contract
- nu face retry pe crearea expedițiilor

## Testare

```bash
composer test
DPD_SANDBOX_USERNAME=... DPD_SANDBOX_PASSWORD=... DPD_SANDBOX_SENDER_CLIENT_ID=... DPD_SANDBOX_SERVICE_ID=... vendor/bin/pest --group=sandbox
```

Fixture-urile sunt după documentație. **De înlocuit cu răspunsuri reale**.

## Changelog

Vezi [CHANGELOG.md](CHANGELOG.md).

## Autor

[WeBRTeu](https://webrteu.eu)

## Licență

MIT. Vezi [LICENSE.md](LICENSE.md).
