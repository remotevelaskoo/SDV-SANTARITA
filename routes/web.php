<?php

use App\Http\Controllers\ProtectedFileController;
use App\Livewire\AccessHistory;
use App\Livewire\AccessValidation;
use App\Livewire\ActiveSessions;
use App\Livewire\AuditLog;
use App\Livewire\CashRegister;
use App\Livewire\CompanyManagement;
use App\Livewire\Dashboard;
use App\Livewire\ForgotPassword;
use App\Livewire\Login;
use App\Livewire\PackageManagement;
use App\Livewire\PersonRegistration;
use App\Livewire\Portaria;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PropertyManagement;
use App\Livewire\PublicPreRegistration;
use App\Livewire\Reports;
use App\Livewire\ResetPassword;
use App\Livewire\UserManagement;
use App\Livewire\VehicleManagement;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/entrar');

Route::get('/entrar', Login::class)->name('login');

Route::post('/sair', function (Request $request, AuditService $audit) {
    $audit->record(
        action: 'saiu',
        module: 'autenticacao',
        entityType: 'sessao',
        entityId: hash('sha256', $request->session()->getId()),
        classification: 'restrita',
    );

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
Route::get('/pre-cadastro/convite-demonstracao', PublicPreRegistration::class)->name('pre-registration.public');
Route::get('/esqueci-minha-senha', ForgotPassword::class)->name('password.request');
Route::get('/redefinir-senha/{token}', ResetPassword::class)->name('password.reset');

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
Route::get('/relatorios', Reports::class)->middleware(['auth', 'permissao:relatorios.proprio.consultar,relatorios.consolidado.consultar'])->name('reports');
Route::get('/usuarios', UserManagement::class)->middleware(['auth', 'permissao:usuarios.administrar'])->name('users');
Route::get('/sessoes', ActiveSessions::class)->middleware('auth')->name('sessions');
Route::get('/auditoria', AuditLog::class)->middleware(['auth', 'permissao:auditoria.consultar'])->name('audit-log');
Route::get('/arquivos/{arquivo}/visualizar', ProtectedFileController::class)
    ->middleware(['auth', 'permissao:arquivos.sensiveis.visualizar'])
    ->name('protected-files.show');

if (app()->environment(['local', 'testing'])) {
    Route::view('/componentes', 'design-system')->name('design-system');
}
