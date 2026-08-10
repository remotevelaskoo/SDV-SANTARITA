<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', Dashboard::class)->name('dashboard');
