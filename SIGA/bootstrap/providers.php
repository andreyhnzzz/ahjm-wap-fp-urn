<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\DomainServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    DomainServiceProvider::class,
];
