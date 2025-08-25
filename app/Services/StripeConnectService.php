<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Transfer;
use Stripe\AccountLink;
use Stripe\Payout;
use Exception;

class StripeConnectService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Create a Stripe Connect Express account for a customer
     */
    public function createConnectedAccount(array $data)
    {
        try {
            $account = Account::create([
                'type' => 'express',
                'country' => $data['country'] ?? 'US',
                'email' => $data['email'],
                'capabilities' => [
                    'transfers' => ['requested' => true],
                    'card_payments' => ['requested' => true],
                ],
                'settings' => [
                    'payouts' => [
                        'schedule' => [
                            'interval' => 'manual', 
                        ],
                    ],
                ],
                'business_type' => 'individual',
                'individual' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                ],
            ]);

            return [
                'success' => true,
                'account_id' => $account->id,
                'account' => $account,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create an account link for onboarding
     */
    public function createAccountLink(string $accountId, string $returnUrl, string $refreshUrl)
    {
        try {
            $accountLink = AccountLink::create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return [
                'success' => true,
                'url' => $accountLink->url,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if account is fully onboarded
     */
    public function isAccountOnboarded(string $accountId)
    {
        try {
            $account = Account::retrieve($accountId);

            return [
                'success' => true,
                'onboarded' => $account->details_submitted && $account->payouts_enabled,
                'account' => $account,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transfer funds from platform to connected account
     */
    public function transferToCustomer(string $accountId, int $amountCents, string $currency = 'usd', array $metadata = [])
    {
        try {
            $transfer = Transfer::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'destination' => $accountId,
                'description' => $metadata['description'] ?? 'Payment from platform',
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'transfer_id' => $transfer->id,
                'transfer' => $transfer,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transfer details
     */
    public function getTransfer(string $transferId)
    {
        try {
            $transfer = Transfer::retrieve($transferId);

            return [
                'success' => true,
                'transfer' => $transfer,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create instant payout to bank account
     */
    public function createInstantPayout(string $accountId, int $amountCents, string $currency = 'usd', array $metadata = [])
    {
        try {
            $payout = Payout::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'method' => 'instant',
                'description' => $metadata['description'] ?? 'Instant payout to bank',
                'metadata' => $metadata,
            ], [
                'stripe_account' => $accountId,
            ]);

            return [
                'success' => true,
                'payout_id' => $payout->id,
                'payout' => $payout,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transfer and instant payout (combined operation)
     */
    public function transferAndPayout(string $accountId, int $amountCents, string $currency = 'usd', array $metadata = [])
    {
        try {
            // First, transfer to connected account
            $transferResult = $this->transferToCustomer($accountId, $amountCents, $currency, $metadata);

            if (!$transferResult['success']) {
                return $transferResult;
            }

            // Wait a moment for transfer to settle
            sleep(2);

            // Then create instant payout to their bank
            $payoutResult = $this->createInstantPayout($accountId, $amountCents, $currency, $metadata);

            return [
                'success' => true,
                'transfer_id' => $transferResult['transfer_id'],
                'payout_id' => $payoutResult['success'] ? $payoutResult['payout_id'] : null,
                'transfer' => $transferResult['transfer'],
                'payout' => $payoutResult['success'] ? $payoutResult['payout'] : null,
                'payout_error' => !$payoutResult['success'] ? $payoutResult['error'] : null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List transfers for an account
     */
    public function listTransfers(string $accountId = null, int $limit = 10)
    {
        try {
            $params = ['limit' => $limit];
            if ($accountId) {
                $params['destination'] = $accountId;
            }

            $transfers = Transfer::all($params);

            return [
                'success' => true,
                'transfers' => $transfers,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
