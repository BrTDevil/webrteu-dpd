<?php

declare(strict_types=1);
use WeBRTeu\Shipping\Exceptions\ShippingException;

arch('nu folosește funcții de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('toate fișierele au strict types')
    ->expect('WeBRTeu\Dpd')
    ->toUseStrictTypes();

arch('fără Guzzle direct: HTTP doar prin Http::')
    ->expect('WeBRTeu\Dpd')
    ->not->toUse('GuzzleHttp');

arch('excepțiile extind ShippingException')
    ->expect('WeBRTeu\Dpd\Exceptions')
    ->toExtend(ShippingException::class);
