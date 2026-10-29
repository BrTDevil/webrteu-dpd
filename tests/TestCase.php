<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd\Tests;

use Illuminate\Database\Migrations\Migration;
use Orchestra\Testbench\TestCase as Orchestra;
use WeBRTeu\Dpd\DpdServiceProvider;
use WeBRTeu\Shipping\ShippingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ShippingServiceProvider::class, DpdServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('dpd.username', env('DPD_SANDBOX_USERNAME', 'test-user'));
        $app['config']->set('dpd.password', env('DPD_SANDBOX_PASSWORD', 'secret-pass'));
        $app['config']->set('dpd.sender_client_id', env('DPD_SANDBOX_SENDER_CLIENT_ID', 12345678901));
        $app['config']->set('dpd.default_service_id', env('DPD_SANDBOX_SERVICE_ID', 2505));
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach ([
            __DIR__.'/../vendor/webrteu/shipping/database/migrations/create_shipments_tables.php.stub',
            __DIR__.'/../database/migrations/create_dpd_tables.php.stub',
        ] as $stub) {
            /** @var Migration $migration */
            $migration = require $stub;
            $migration->up();
        }
    }
}
