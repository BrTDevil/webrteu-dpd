<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Dpd\DpdCarrier;
use WeBRTeu\Dpd\Models\DpdOffice;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Enums\PickupPointType;

class SyncOfficesCommand extends Command
{
    protected $signature = 'dpd:sync-offices';

    protected $description = 'Sincronizează oficiile DPD și lockerele DPD Box în dpd_offices';

    public function handle(DpdCarrier $dpd): int
    {
        $offices = $dpd->remotePickupPoints();

        foreach ($offices as $office) {
            DpdOffice::query()->updateOrCreate(['office_id' => (int) $office->id], [
                'type' => $office->type === PickupPointType::Locker ? 'APT' : 'OFFICE',
                'name' => $office->name,
                'county' => $office->county !== '' ? $office->county : null,
                'city' => $office->city,
                'address' => $office->address,
                'postal_code' => $office->postalCode,
                'lat' => $office->lat,
                'lng' => $office->lng,
                'supports_cod' => $office->supportsCod,
                'active' => true,
                'raw' => $office->raw,
            ]);
        }

        $deactivated = $offices->isEmpty() ? 0 : DpdOffice::query()
            ->whereNotIn('office_id', $offices->map(fn (PickupPointData $office): int => (int) $office->id)->all())
            ->update(['active' => false]);

        $this->info("Oficii sincronizate: {$offices->count()}, dezactivate: {$deactivated}.");

        return self::SUCCESS;
    }
}
