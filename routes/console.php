<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\StripeConnectService;
use App\Models\ConnectedAccount;
use App\Models\TransferRecord;

Artisan::command('test:stripe', function () {
    $stripe = app(StripeConnectService::class);
    
    try {
        // Test creating an account
        $result = $stripe->createConnectedAccount([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'country' => 'US',
        ]);
        
        if ($result['success']) {
            $this->info('✅ Stripe Connect is working!');
            $this->info('Account ID: ' . $result['account_id']);
        } else {
            $this->error('❌ Stripe Connect failed: ' . $result['error']);
        }
    } catch (Exception $e) {
        $this->error('❌ Error: ' . $e->getMessage());
    }
})->purpose('Test Stripe Connect integration');

Artisan::command('stripe:status', function () {
    $this->info('📊 Current Stripe Connect Status:');
    $this->newLine();
    
    // Show connected accounts
    $accounts = ConnectedAccount::all();
    $this->info("Connected Accounts: {$accounts->count()}");
    
    foreach ($accounts as $account) {
        $status = $account->onboarded ? '✅ Onboarded' : '⏳ Pending';
        $this->line("- {$account->full_name} ({$account->email}): {$status}");
        $this->line("  Stripe ID: {$account->stripe_account_id}");
    }
    
    $this->newLine();
    
    // Show transfers
    $transfers = TransferRecord::with('connectedAccount')->get();
    $this->info("Total Transfers: {$transfers->count()}");
    $totalAmount = $transfers->sum('amount_cents') / 100;
    $this->info("Total Amount: \${$totalAmount}");
    
    foreach ($transfers as $transfer) {
        $amount = number_format($transfer->amount_cents / 100, 2);
        $this->line("- \${$amount} to {$transfer->connectedAccount->full_name} ({$transfer->status})");
        $this->line("  Stripe Transfer ID: {$transfer->stripe_transfer_id}");
    }
})->purpose('Show current Stripe Connect status');
