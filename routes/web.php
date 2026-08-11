<?php

use App\Livewire\AccessHistory;
use App\Livewire\AccessValidation;
use App\Livewire\CashRegister;
use App\Livewire\CompanyManagement;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\PackageManagement;
use App\Livewire\PersonRegistration;
use App\Livewire\Portaria;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PropertyManagement;
use App\Livewire\PublicPreRegistration;
use App\Livewire\VehicleManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

Route::get('/entrar', Login::class)->name('login');

Route::post('/sair', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
Route::get('/dashboard', Dashboard::class)->name('dashboard');
Route::get('/portaria', Portaria::class)->name('portaria');
Route::get('/validacao', AccessValidation::class)->name('validation');
Route::get('/entradas-saidas', AccessHistory::class)->name('access-history');
Route::get('/pre-cadastros', PreRegistrationQueue::class)->name('pre-registrations');
Route::get('/pre-cadastro/convite-demonstracao', PublicPreRegistration::class)->name('pre-registration.public');
Route::get('/imoveis', PropertyManagement::class)->name('properties');
Route::get('/veiculos', VehicleManagement::class)->name('vehicles');
Route::get('/pessoas/nova', PersonRegistration::class)->name('people.create');
Route::get('/empresas', CompanyManagement::class)->name('companies');
Route::get('/caixa', CashRegister::class)->name('cash-register');
Route::get('/encomendas', PackageManagement::class)->name('packages');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
