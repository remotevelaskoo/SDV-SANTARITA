<?php

use App\Livewire\AccessHistory;
use App\Livewire\AccessValidation;
use App\Livewire\ActiveSessions;
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
Route::get('/pre-cadastro/convite-demonstracao', PublicPreRegistration::class)->name('pre-registration.public');

Route::get('/dashboard', Dashboard::class)->middleware('auth')->name('dashboard');
Route::get('/portaria', Portaria::class)->middleware(['auth', 'permissao:validacao.registrar'])->name('portaria');
Route::get('/validacao', AccessValidation::class)->middleware(['auth', 'permissao:validacao.registrar'])->name('validation');
Route::get('/entradas-saidas', AccessHistory::class)->middleware(['auth', 'permissao:validacao.registrar'])->name('access-history');
Route::get('/pre-cadastros', PreRegistrationQueue::class)->middleware(['auth', 'permissao:pre-registro.analisar'])->name('pre-registrations');
Route::get('/imoveis', PropertyManagement::class)->middleware(['auth', 'permissao:imoveis.consultar'])->name('properties');
Route::get('/veiculos', VehicleManagement::class)->middleware(['auth', 'permissao:veiculos.consultar'])->name('vehicles');
Route::get('/pessoas/nova', PersonRegistration::class)->middleware(['auth', 'permissao:pessoas.gerenciar'])->name('people.create');
Route::get('/empresas', CompanyManagement::class)->middleware(['auth', 'permissao:empresas.consultar'])->name('companies');
Route::get('/caixa', CashRegister::class)->middleware(['auth', 'permissao:caixa.proprio.gerenciar'])->name('cash-register');
Route::get('/encomendas', PackageManagement::class)->middleware(['auth', 'permissao:encomendas.registrar'])->name('packages');
Route::get('/sessoes', ActiveSessions::class)->middleware('auth')->name('sessions');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
