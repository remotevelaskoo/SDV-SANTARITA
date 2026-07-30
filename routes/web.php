<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/health');

Route::get('/health', static fn (): array => [
    'status' => 'ok',
    'service' => 'sdv-access',
]);
