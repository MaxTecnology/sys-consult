<?php

use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;

// Redireciona a raiz para o painel Filament
Route::get('/', function () {
    $panel = Filament::getPanel('app');
    return $panel ? redirect()->to($panel->getUrl()) : redirect()->to('/app');
});
