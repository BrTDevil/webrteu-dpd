<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Dpd\DpdClient;

class ContractCommand extends Command
{
    protected $signature = 'dpd:contract';

    protected $description = 'Afișează clientId-urile din contractul DPD (pentru DPD_SENDER_CLIENT_ID)';

    public function handle(DpdClient $client): int
    {
        $json = $client->json('client/contract/');
        $rows = [];

        foreach (is_array($json['clients'] ?? null) ? $json['clients'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                is_scalar($row['clientId'] ?? null) ? (string) $row['clientId'] : '',
                is_scalar($row['clientName'] ?? null) ? (string) $row['clientName'] : '',
                is_scalar($row['objectName'] ?? null) ? (string) $row['objectName'] : '',
                is_scalar(data_get($row, 'address.fullAddressString')) ? (string) data_get($row, 'address.fullAddressString') : '',
            ];
        }

        $this->table(['clientId', 'Client', 'Obiect', 'Adresă'], $rows);

        return self::SUCCESS;
    }
}
