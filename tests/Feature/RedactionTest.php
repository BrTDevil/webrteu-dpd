<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Dpd\Exceptions\DpdException;
use WeBRTeu\Shipping\Facades\Shipping;

it('never logs the password or the user name', function (): void {
    Log::spy();
    Http::fake([DPD.'*' => Http::response(['error' => ['message' => 'Invalid credentials', 'code' => 1]])]);

    expect(fn () => Shipping::carrier('dpd')->track('1'))->toThrow(DpdException::class, 'Invalid credentials');

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        $logged = (string) json_encode($context);

        return $message === 'dpd.request_failed' && ! str_contains($logged, 'secret-pass') && ! str_contains($logged, 'test-user');
    });
});
