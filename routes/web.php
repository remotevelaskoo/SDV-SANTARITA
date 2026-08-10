<?php

use App\Livewire\AccessValidation;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\Portaria;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

Route::get('/entrar', Login::class)->name('login');
Route::get('/dashboard', Dashboard::class)->name('dashboard');
Route::get('/portaria', Portaria::class)->name('portaria');
Route::get('/validacao', AccessValidation::class)->name('validation');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
