<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Dpd\DpdClient;
use WeBRTeu\Dpd\DpdServiceProvider;
use WeBRTeu\Dpd\Facades\Dpd;
use WeBRTeu\Dpd\Models\DpdOffice;
use WeBRTeu\Dpd\Models\DpdSite;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Facades\Shipping;

it('syncs the sites from the CSV nomenclature', function (): void {
    Http::fake([DPD.'location/site/csv/642' => Http::response(fixtureFile('site-search.csv'), 200, ['Content-Type' => 'text/csv'])]);

    $this->artisan('dpd:sync-locations')->expectsOutput('Localități sincronizate: 2.');

    expect(DpdSite::query()->where('site_id', 642279133)->sole())
        ->name_key->toBe('rasnov')
        ->post_code->toBeNull()
        ->getTable()->toBe('dpd_sites');
});

it('syncs offices and DPD Box lockers, then filters them', function (): void {
    DpdOffice::query()->create(['office_id' => 1, 'name' => 'x', 'city' => 'x', 'address' => 'x']);
    Http::fake([DPD.'location/office/' => Http::response(fixtureFile('offices.json'))]);

    $this->artisan('dpd:sync-offices')->expectsOutput('Oficii sincronizate: 2, dezactivate: 1.');

    $box = Shipping::carrier('dpd')->pickupPoints('Brașov', 'Brașov');

    expect($box)->toHaveCount(1)
        ->and($box[0]->type)->toBe(PickupPointType::Locker)
        ->and($box[0]->supportsCod)->toBeTrue()
        ->and($box[0]->lat)->toBe(45.64)
        ->and(DpdOffice::query()->where('office_id', 902)->sole())->type->toBe('OFFICE')->getTable()->toBe('dpd_offices');
});

it('reads offices from the API until synced', function (): void {
    Http::fake([DPD.'location/office/' => Http::response(fixtureFile('offices.json'))]);

    $offices = Shipping::carrier('dpd')->pickupPoints(city: 'Râșnov');

    expect($offices)->toHaveCount(1)
        ->and($offices[0])->type->toBe(PickupPointType::Office)->county->toBe('BRASOV')->address->toBe('Str. Republicii 2')->postalCode->toBeNull();
});

it('does not deactivate anything on an empty response', function (): void {
    Http::fake([DPD.'location/office/' => Http::response(['offices' => []])]);

    $this->artisan('dpd:sync-offices')->expectsOutput('Oficii sincronizate: 0, dezactivate: 0.');
});

it('prints the contract clients', function (): void {
    Http::fake([DPD.'client/contract/' => Http::response(fixtureFile('contract.json'))]);

    $this->artisan('dpd:contract')->expectsTable(['clientId', 'Client', 'Obiect', 'Adresă'], [
        ['12345678901', 'Magazin SRL', 'Depozit Brasov', 'Str. Fabricii 5, Brasov'],
    ]);
});

it('registers the carrier', function (): void {
    expect(Shipping::available())->toContain('dpd')
        ->and(Dpd::client())->toBeInstanceOf(DpdClient::class)
        ->and(ServiceProvider::pathsToPublish(DpdServiceProvider::class, 'dpd-migrations'))->toHaveCount(1);
});
