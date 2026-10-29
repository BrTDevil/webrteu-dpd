<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Facades\Shipping;

it('adds userName, password and language to every request', function (): void {
    Http::fake([DPD.'*' => Http::response(fixtureFile('track.json'))]);

    Shipping::carrier('dpd')->track('80123456789');

    Http::assertSent(fn (Request $request): bool => $request->url() === DPD.'track/'
        && $request['userName'] === 'test-user'
        && $request['password'] === 'secret-pass'
        && $request['language'] === 'RO'
        && ! isset($request['clientSystemId']));
});

it('adds clientSystemId when configured', function (): void {
    Config::set('dpd.client_system_id', '77');
    Http::fake([DPD.'*' => Http::response(fixtureFile('track.json'))]);

    Shipping::carrier('dpd')->track('80123456789');

    Http::assertSent(fn (Request $request): bool => $request['clientSystemId'] === 77);
});
