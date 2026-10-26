<?php

declare(strict_types=1);

arch()->preset()->php();
// Filament resources and pages extend framework classes whose hooks are protected
arch()->preset()->strict()->ignoring('App\Filament');
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
