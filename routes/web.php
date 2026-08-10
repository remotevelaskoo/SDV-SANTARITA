<?php

use App\Livewire\Dashboard;
use App\Livewire\Login;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

Route::get('/entrar', Login::class)->name('login');
Route::get('/dashboard', Dashboard::class)->name('dashboard');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
