<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Dpd\DpdClient;
use WeBRTeu\Dpd\Models\DpdSite;
use WeBRTeu\Dpd\Requests\ShipmentPayload;
use WeBRTeu\Shipping\Support\Normalizer;

class SyncLocationsCommand extends Command
{
    protected $signature = 'dpd:sync-locations';

    protected $description = 'Sincronizează localitățile DPD din România (CSV) în dpd_sites';

    /**
     * ⚠️ Nomenclatorul complet vine ca CSV din location/site/csv/{countryId}; coloanele se confirmă în docs.
     */
    public function handle(DpdClient $client): int
    {
        $csv = $client->send('location/site/csv/'.ShipmentPayload::COUNTRY_RO)->body();
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        $header = array_map(strval(...), str_getcsv((string) array_shift($lines), escape: '\\'));
        $synced = 0;

        foreach ($lines as $line) {
            $values = str_getcsv($line, escape: '\\');

            if (count($values) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $values);

            if (! is_numeric($row['id'] ?? null) || ($row['name'] ?? '') === '') {
                continue;
            }

            DpdSite::query()->updateOrCreate(['site_id' => (int) $row['id']], [
                'name' => (string) $row['name'],
                'municipality' => ($row['municipality'] ?? '') !== '' ? $row['municipality'] : null,
                'region' => ($row['region'] ?? '') !== '' ? $row['region'] : null,
                'post_code' => ($row['postCode'] ?? '') !== '' ? $row['postCode'] : null,
                'name_key' => Normalizer::key((string) $row['name']),
                'region_key' => Normalizer::key((string) ($row['region'] ?? '')),
            ]);

            $synced++;
        }

        $this->info("Localități sincronizate: {$synced}.");

        return self::SUCCESS;
    }
}
