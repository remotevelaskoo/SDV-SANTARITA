<?php

use App\Livewire\AccessValidation;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\PersonRegistration;
use App\Livewire\Portaria;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PropertyManagement;
use App\Livewire\PublicPreRegistration;
use App\Livewire\VehicleManagement;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

Route::get('/entrar', Login::class)->name('login');
Route::get('/dashboard', Dashboard::class)->name('dashboard');
Route::get('/portaria', Portaria::class)->name('portaria');
Route::get('/validacao', AccessValidation::class)->name('validation');
Route::get('/pre-cadastros', PreRegistrationQueue::class)->name('pre-registrations');
Route::get('/pre-cadastro/convite-demonstracao', PublicPreRegistration::class)->name('pre-registration.public');
Route::get('/imoveis', PropertyManagement::class)->name('properties');
Route::get('/veiculos', VehicleManagement::class)->name('vehicles');
Route::get('/pessoas/nova', PersonRegistration::class)->name('people.create');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
