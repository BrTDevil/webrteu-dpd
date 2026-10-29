<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Dpd\Commands\ContractCommand;
use WeBRTeu\Dpd\Commands\SyncLocationsCommand;
use WeBRTeu\Dpd\Commands\SyncOfficesCommand;
use WeBRTeu\Shipping\ShippingManager;

class DpdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dpd.php', 'dpd');

        $this->app->singleton(DpdClient::class);
        $this->app->singleton(DpdCarrier::class);

        $this->callAfterResolving(ShippingManager::class, function (ShippingManager $shipping): void {
            $shipping->extend('dpd', fn (Container $app): DpdCarrier => $app->make(DpdCarrier::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/dpd.php' => $this->app->configPath('dpd.php'),
        ], 'dpd-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_dpd_tables.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_dpd_tables.php'),
        ], 'dpd-migrations');

        $this->commands([SyncLocationsCommand::class, SyncOfficesCommand::class, ContractCommand::class]);
    }
}
