<?php

declare(strict_types=1);

arch('application must follow the defined architectural rules')
    ->preset()
    ->laravel()
    ->ignoring('App\Providers');

arch('application must follow the defined PHP architectural rules')
    ->preset()
    ->php();

arch('application must follow security best practices')
    ->preset()
    ->security();
