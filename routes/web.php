<?php

use Illuminate\Support\Facades\Route;


use App\Services\PlaidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StripeController;

use App\Http\Controllers\PlaidController;
use App\Http\Controllers\PayoutDemoController;

Route::middleware('auth')->prefix('plaid')->group(function () {
    Route::get('/connect', [PlaidController::class, 'showLinkPage'])->name('plaid.connect');
    Route::post('/exchange', [PlaidController::class, 'exchangeToken'])->name('plaid.exchange');
    Route::get('/transactions', [PlaidController::class, 'showTransactions'])->name('plaid.transactions');

    Route::post('/sync-transactions/{bankConnection}', [PlaidController::class, 'syncTransactions'])->name('plaid.sync-transactions');
    Route::post('/sync-accounts/{bankConnection}', [PlaidController::class, 'syncAccounts'])->name('plaid.sync-accounts');
});

Route::middleware('auth')->prefix('stripe')->group(function () {
    Route::post('/create-payment-method/{bankAccount}', [StripeController::class, 'createPaymentMethod'])->name('stripe.create-payment-method');
    Route::post('/create-setup-intent', [StripeController::class, 'createSetupIntent'])->name('stripe.create-setup-intent');
    Route::post('/verify-bank-account/{bankAccount}', [StripeController::class, 'verifyBankAccount'])->name('stripe.verify-bank-account');
});

Route::get('/dashboard', [StripeController::class, 'dashboard'])->name('dashboard');

// Billing routes
Route::middleware('auth')->prefix('billing')->name('billing.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\BillingController::class, 'dashboard'])->name('dashboard');
    Route::get('/invoices', [App\Http\Controllers\BillingController::class, 'indexInvoices'])->name('invoices');
    Route::get('/invoice/{id}', [App\Http\Controllers\BillingController::class, 'showInvoice'])->name('invoice');
    Route::get('/pay/{id}', [App\Http\Controllers\BillingController::class, 'showPaymentForm'])->name('pay');
    Route::post('/pay/{id}', [App\Http\Controllers\BillingController::class, 'processPayment'])->name('process-payment');
    Route::get('/rental/{id}', [App\Http\Controllers\BillingController::class, 'showRental'])->name('rental');


    // API endpoints
    Route::get('/api/rental/{id}', [App\Http\Controllers\BillingController::class, 'getRentalApi'])->name('api.rental');
});

// Payout Demo routes
Route::prefix('payout-demo')->group(function () {
    Route::get('/', [PayoutDemoController::class, 'dashboard'])->name('payout-demo.dashboard');
    Route::post('/customer', [PayoutDemoController::class, 'createCustomer'])->name('payout-demo.create-customer');
    Route::get('/onboard-return', [PayoutDemoController::class, 'onboardReturn'])->name('payout-demo.onboard-return');
    Route::get('/onboard-refresh', [PayoutDemoController::class, 'onboardRefresh'])->name('payout-demo.onboard-refresh');
    Route::post('/transfer', [PayoutDemoController::class, 'transfer'])->name('payout-demo.transfer');
    Route::get('/transfers/{accountId}', [PayoutDemoController::class, 'getTransfers'])->name('payout-demo.transfers');
    Route::get('/car-owner-dashboard', [PayoutDemoController::class, 'carOwnerDashboard'])->name('payout-demo.car-owner-dashboard');
    Route::get('/dashboard-refresh', [PayoutDemoController::class, 'dashboardRefresh'])->name('payout-demo.dashboard-refresh');
    Route::get('/bank-accounts/{account_id}', [PayoutDemoController::class, 'getBankAccounts'])->name('payout-demo.bank-accounts');
    Route::post('/bank-accounts/{account_id}', [PayoutDemoController::class, 'addBankAccount'])->name('payout-demo.add-bank-account');
    Route::post('/cards/{account_id}', [PayoutDemoController::class, 'addCard'])->name('payout-demo.add-card');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
