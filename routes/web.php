<?php

use Illuminate\Support\Facades\Route;


use App\Services\PlaidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\PlaidController;

Route::middleware('auth')->group(function () {
    Route::get('/plaid/connect', [PlaidController::class, 'showLinkPage'])->name('plaid.connect');
    Route::post('/plaid/exchange', [PlaidController::class, 'exchangeToken'])->name('plaid.exchange');
    Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
