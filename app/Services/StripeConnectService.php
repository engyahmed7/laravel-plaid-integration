<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Transfer;
use Stripe\AccountLink;
use Stripe\Payout;
use App\Models\ConnectedAccount;
use Stripe\BalanceTransaction;
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
    public function payoutToCard(string $accountId, string $cardId, int $amountCents, string $currency = 'usd', array $metadata = [])
    {
        try {
            $payout = Payout::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'method' => 'instant',
                'destination' => $cardId,
                'description' => $metadata['description'] ?? 'Instant payout to debit card',
                'metadata' => $metadata,
            ], [
                'stripe_account' => $accountId,
            ]);

            return [
                'success' => true,
                'payout_id' => $payout->id,
                'payout' => $payout,
                'destination_type' => 'card',
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


    public function addBankAccount(string $accountId, array $bankData)
    {
        try {
            $account = Account::retrieve($accountId);
            $bankAccount = $account->external_accounts->create([
                'external_account' => [
                    'object' => 'bank_account',
                    'account_number' => $bankData['account_number'],
                    'routing_number' => $bankData['routing_number'],
                    'country' => $bankData['country'] ?? 'US',
                    'currency' => $bankData['currency'] ?? 'usd',
                    'account_holder_name' => $bankData['account_holder_name'],
                    'account_holder_type' => $bankData['account_holder_type'] ?? 'individual',
                ],
            ]);

            return [
                'success' => true,
                'bank_account_id' => $bankAccount->id,
                'bank_account' => $bankAccount,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add card to connected account
     */
    public function addCard(string $accountId, array $cardData)
    {
        try {
            $account = Account::retrieve($accountId);
            $card = $account->external_accounts->create([
                'external_account' => [
                    'object' => 'card',
                    'number' => $cardData['number'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                    'cvc' => $cardData['cvc'],
                    'name' => $cardData['name'],
                    'address_line1' => $cardData['address_line1'] ?? null,
                    'address_city' => $cardData['address_city'] ?? null,
                    'address_state' => $cardData['address_state'] ?? null,
                    'address_zip' => $cardData['address_zip'] ?? null,
                    'address_country' => $cardData['address_country'] ?? 'US',
                ],
            ]);

            return [
                'success' => true,
                'card_id' => $card->id,
                'card' => $card,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }


    /**
     * Get available bank accounts for connected account
     */
    public function getExternalAccounts(string $accountId)
    {
        try {
            $account = Account::retrieve($accountId);
            $allExternalAccounts = $account->external_accounts->all();

            $bankAccounts = [];
            $cards = [];

            foreach ($allExternalAccounts->data as $externalAccount) {
                if ($externalAccount->object === 'bank_account') {
                    $bankAccounts[] = $externalAccount;
                } elseif ($externalAccount->object === 'card') {
                    $cards[] = $externalAccount;
                }
            }
            return [
                'success' => true,
                'accounts' => $externalAccounts->data,
                'default_account' => $account->default_for_currency['usd'] ?? null,
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
    public function createInstantPayout(string $accountId, int $amountCents, string $currency = 'usd', array $metadata = [], string $destinationId = null)
    {
        try {
            $payoutData = [
                'amount' => $amountCents,
                'currency' => $currency,
                'method' => 'instant',
                'description' => $metadata['description'] ?? 'Instant payout to bank',
                'metadata' => $metadata,
            ];

            // Specify destination bank account if provided
            if ($destinationId) {
                $payoutData['destination'] = $destinationId;
            }

            $payout = Payout::create($payoutData, [
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
    public function transferAndPayout(string $accountId, int $amountCents, string $currency = 'usd', array $metadata = [], string $destinationId = null)
    {
        try {
            $transferResult = $this->transferToCustomer($accountId, $amountCents, $currency, $metadata);

            if (!$transferResult['success']) {
                return $transferResult;
            }

            sleep(2);

            $payoutResult = $this->createInstantPayout($accountId, $amountCents, $currency, $metadata, $destinationId);
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
     * Create dashboard link for connected account
     */
    public function createDashboardLink(string $accountId)
    {
        try {
            $accountLink = AccountLink::create([
                'account' => $accountId,
                'refresh_url' => url('/payout-demo/dashboard-refresh?account_id=' . $accountId),
                'return_url' => url('/payout-demo'),
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
     * Create login link for connected account dashboard
     */
    public function createLoginLink(string $accountId)
    {
        try {
            $loginLink = Account::createLoginLink($accountId);

            return [
                'success' => true,
                'url' => $loginLink->url,
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

    /**
     * Get all transactions for all connected accounts
     */

    public function getAllConnectedAccountsTransactions(int $limit = 50, array $filters = [])
    {
        $results = [];

        try {
            $connectedAccounts = ConnectedAccount::all();

            foreach ($connectedAccounts as $account) {
                try {
                    $params = array_merge(['limit' => $limit], $filters);

                    $transactions = BalanceTransaction::all(
                        $params,
                        ['stripe_account' => $account->stripe_account_id]
                    );

                    foreach ($transactions->data as $transaction) {
                        $results[] = [
                            'account_id' => $account->stripe_account_id,
                            'amount'     => $transaction->amount,
                            'currency'   => $transaction->currency,
                            'status'     => $transaction->status,
                            'type'       => $transaction->type,
                            'created'    => $transaction->created,
                        ];
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'account_id' => $account->stripe_account_id,
                        'error'      => $e->getMessage(),
                    ];
                }
            }

            return [
                'success' => true,
                'data'    => $results,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
