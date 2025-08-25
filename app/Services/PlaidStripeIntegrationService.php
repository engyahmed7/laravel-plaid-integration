<?php

namespace App\Services;

use App\Models\User;
use App\Models\BankConnection;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PlaidStripeIntegrationService
{
    protected PlaidService $plaidService;
    protected StripeService $stripeService;

    public function __construct(PlaidService $plaidService, StripeService $stripeService)
    {
        $this->plaidService = $plaidService;
        $this->stripeService = $stripeService;
    }

    public function initiateBankConnection(User $user)
    {
        try {
            $linkToken = $this->plaidService->createLinkToken($user->id);

            if (!$linkToken || !isset($linkToken['link_token'])) {
                throw new Exception('Failed to create Plaid link token');
            }

            return [
                'success' => true,
                'link_token' => $linkToken['link_token'],
            ];
        } catch (Exception $e) {
            Log::error('Failed to initiate bank connection', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function completeBankConnection(User $user, string $publicToken, array $metadata = [])
    {
        DB::beginTransaction();

        try {
            // Exchange public token for access token
            $exchangeResponse = $this->plaidService->exchangePublicToken($publicToken);

            if (!$exchangeResponse || !isset($exchangeResponse['access_token'])) {
                throw new Exception('Failed to exchange public token');
            }

            $accessToken = $exchangeResponse['access_token'];
            $itemId = $exchangeResponse['item_id'];

            // Create or get Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            // Create bank connection record
            $bankConnection = BankConnection::create([
                'user_id' => $user->id,
                'plaid_access_token' => $accessToken,
                'plaid_item_id' => $itemId,
                'plaid_public_token' => $publicToken,
                'stripe_customer_id' => $stripeCustomer->id,
                'institution_name' => $metadata['institution']['name'] ?? null,
                'institution_id' => $metadata['institution']['institution_id'] ?? null,
                'status' => 'active',
            ]);

            // Sync accounts and create payment methods
            $this->syncBankAccounts($bankConnection);

            DB::commit();

            return [
                'success' => true,
                'bank_connection' => $bankConnection->load('accounts'),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to complete bank connection', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function syncBankAccounts(BankConnection $bankConnection)
    {
        try {
            // Get accounts from Plaid
            $accountsResponse = $this->plaidService->getAccounts($bankConnection->plaid_access_token);

            if (!$accountsResponse || !isset($accountsResponse['accounts'])) {
                throw new Exception('Failed to retrieve accounts from Plaid');
            }

            $accounts = $accountsResponse['accounts'];
            $bankConnection->update(['accounts_count' => count($accounts)]);

            foreach ($accounts as $plaidAccount) {
                $this->createOrUpdateBankAccount($bankConnection, $plaidAccount);
            }

            $bankConnection->update(['last_sync_at' => now()]);

            return [
                'success' => true,
                'accounts_synced' => count($accounts),
            ];
        } catch (Exception $e) {
            Log::error('Failed to sync bank accounts', [
                'bank_connection_id' => $bankConnection->id,
                'error' => $e->getMessage()
            ]);

            $bankConnection->update([
                'status' => 'error',
                'error_code' => 'SYNC_ACCOUNTS_FAILED',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function syncTransactions(BankConnection $bankConnection, Carbon $startDate = null, Carbon $endDate = null)
    {
        try {
            $startDate = $startDate ?: now()->subDays(30);
            $endDate = $endDate ?: now();

            $transactionsResponse = $this->plaidService->getTransactions(
                $bankConnection->plaid_access_token,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            if (!$transactionsResponse || !isset($transactionsResponse['transactions'])) {
                throw new Exception('Failed to retrieve transactions from Plaid');
            }

            $transactions = $transactionsResponse['transactions'];
            $syncedCount = 0;

            foreach ($transactions as $plaidTransaction) {
                if ($this->createOrUpdateTransaction($bankConnection, $plaidTransaction)) {
                    $syncedCount++;
                }
            }

            return [
                'success' => true,
                'transactions_synced' => $syncedCount,
                'total_transactions' => count($transactions),
            ];
        } catch (Exception $e) {
            Log::error('Failed to sync transactions', [
                'bank_connection_id' => $bankConnection->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createStripePaymentMethod(BankAccount $bankAccount)
    {
        try {
            if ($bankAccount->stripe_payment_method_id) {
                return [
                    'success' => true,
                    'payment_method_id' => $bankAccount->stripe_payment_method_id,
                    'message' => 'Payment method already exists',
                ];
            }

            $bankConnection = $bankAccount->bankConnection;

            $stripeAccountType = $this->mapAccountTypeForStripe($bankAccount->account_type, $bankAccount->account_subtype);
            $user = $bankConnection->user;

            $paymentMethod = $this->stripeService->createBankAccountPaymentMethod(
                $bankConnection->stripe_customer_id,
                '415-555-0011', // In production, use real account number from verification
                $bankAccount->routing_number,
                $stripeAccountType,
                $user->name
            );

            $bankAccount->update([
                'stripe_payment_method_id' => $paymentMethod->id,
            ]);

            return [
                'success' => true,
                'payment_method_id' => $paymentMethod->id,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Stripe payment method', [
                'bank_account_id' => $bankAccount->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getOrCreateStripeCustomer(User $user)
    {
        if ($user->stripe_customer_id) {
            return $this->stripeService->getCustomer($user->stripe_customer_id);
        }

        $customer = $this->stripeService->createCustomer(
            $user->email,
            $user->name,
            ['laravel_user_id' => $user->id]
        );

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    protected function createOrUpdateBankAccount(BankConnection $bankConnection, array $plaidAccount)
    {
        $bankAccount = BankAccount::updateOrCreate(
            ['plaid_account_id' => $plaidAccount['account_id']],
            [
                'bank_connection_id' => $bankConnection->id,
                'account_name' => $plaidAccount['name'],
                'account_type' => $plaidAccount['type'],
                'account_subtype' => $plaidAccount['subtype'] ?? null,
                'mask' => $plaidAccount['mask'] ?? null,
                'balance_available' => $plaidAccount['balances']['available'] ?? null,
                'balance_current' => $plaidAccount['balances']['current'] ?? null,
                'balance_limit' => $plaidAccount['balances']['limit'] ?? null,
                'currency_code' => $plaidAccount['balances']['iso_currency_code'] ?? 'USD',
            ]
        );

        return $bankAccount;
    }

    protected function createOrUpdateTransaction(BankConnection $bankConnection, array $plaidTransaction)
    {
        $bankAccount = BankAccount::where('plaid_account_id', $plaidTransaction['account_id'])->first();

        if (!$bankAccount) {
            Log::warning('Bank account not found for transaction', [
                'plaid_account_id' => $plaidTransaction['account_id'],
                'transaction_id' => $plaidTransaction['transaction_id']
            ]);
            return false;
        }

        $transaction = Transaction::updateOrCreate(
            ['plaid_transaction_id' => $plaidTransaction['transaction_id']],
            [
                'bank_connection_id' => $bankConnection->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => -$plaidTransaction['amount'], // Plaid uses positive for debits
                'currency_code' => $plaidTransaction['iso_currency_code'] ?? 'USD',
                'description' => $plaidTransaction['name'],
                'merchant_name' => $plaidTransaction['merchant_name'] ?? null,
                'category' => $plaidTransaction['category'][0] ?? null,
                'subcategory' => $plaidTransaction['category'][1] ?? null,
                'transaction_type' => $plaidTransaction['transaction_type'] ?? null,
                'transaction_date' => $plaidTransaction['date'],
                'authorized_date' => $plaidTransaction['authorized_date'] ?? null,
                'account_owner' => $plaidTransaction['account_owner'] ?? null,
                'location_address' => $plaidTransaction['location']['address'] ?? null,
                'location_city' => $plaidTransaction['location']['city'] ?? null,
                'location_region' => $plaidTransaction['location']['region'] ?? null,
                'location_postal_code' => $plaidTransaction['location']['postal_code'] ?? null,
                'location_country' => $plaidTransaction['location']['country'] ?? null,
                'payment_channel' => $plaidTransaction['payment_channel'] ?? null,
                'pending' => $plaidTransaction['pending'] ?? false,
                'synced_at' => now(),
            ]
        );

        return true;
    }

    protected function mapAccountTypeForStripe(string $accountType, ?string $accountSubtype = null): string
    {
        // Map Plaid account types/subtypes to Stripe account types
        // Stripe only accepts 'checking' or 'savings'

        $lowerAccountType = strtolower($accountType);
        $lowerSubtype = $accountSubtype ? strtolower($accountSubtype) : null;

        // Check subtype first for more specific mapping
        if ($lowerSubtype) {
            switch ($lowerSubtype) {
                case 'checking':
                    return 'checking';
                case 'savings':
                    return 'savings';
                case 'money market':
                case 'cd':
                case 'certificate of deposit':
                    return 'savings';
                default:
                    // Fall through to account type mapping
                    break;
            }
        }

        // Map based on account type
        switch ($lowerAccountType) {
            case 'depository':
            case 'checking':
                return 'checking';
            case 'savings':
                return 'savings';
            default:
                // Default to checking for unknown types
                return 'checking';
        }
    }
}
