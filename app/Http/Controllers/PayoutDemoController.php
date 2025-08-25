<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeConnectService;
use App\Models\ConnectedAccount;
use App\Models\TransferRecord;
use Illuminate\Support\Facades\Log;

class PayoutDemoController extends Controller
{
    protected $stripeConnect;

    public function __construct(StripeConnectService $stripeConnect)
    {
        $this->stripeConnect = $stripeConnect;
    }

    public function dashboard()
    {
        $accounts = ConnectedAccount::with('transferRecords')->get();
        $totalTransfers = TransferRecord::sum('amount_cents') / 100;

        return view('payout-demo.dashboard', compact('accounts', 'totalTransfers'));
    }

    public function createCustomer(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'country' => 'string|size:2',
        ]);

        $result = $this->stripeConnect->createConnectedAccount([
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'country' => $request->country ?? 'US',
        ]);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        $account = ConnectedAccount::create([
            'stripe_account_id' => $result['account_id'],
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'country' => $request->country ?? 'US',
        ]);

        $linkResult = $this->stripeConnect->createAccountLink(
            $result['account_id'],
            url('/payout-demo/onboard-return?account_id=' . $account->id),
            url('/payout-demo/onboard-refresh?account_id=' . $account->id)
        );

        return response()->json([
            'account_id' => $account->id,
            'onboard_url' => $linkResult['url'],
        ]);
    }

    public function onboardReturn(Request $request)
    {
        $account = ConnectedAccount::findOrFail($request->account_id);

        $result = $this->stripeConnect->isAccountOnboarded($account->stripe_account_id);

        if ($result['success']) {
            $account->update([
                'onboarded' => $result['onboarded'],
                'payouts_enabled' => $result['account']->payouts_enabled ?? false,
            ]);
        }

        return redirect('/payout-demo')->with('success', 'Account onboarding completed!');
    }

    public function onboardRefresh(Request $request)
    {
        $account = ConnectedAccount::findOrFail($request->account_id);

        $linkResult = $this->stripeConnect->createAccountLink(
            $account->stripe_account_id,
            url('/payout-demo/onboard-return?account_id=' . $account->id),
            url('/payout-demo/onboard-refresh?account_id=' . $account->id)
        );

        if ($linkResult['success']) {
            return redirect($linkResult['url']);
        }

        return redirect('/payout-demo')->with('error', 'Failed to refresh onboarding link');
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:connected_accounts,id',
            'amount' => 'required|numeric|min:0.50',
            'description' => 'string|max:255',
        ]);

        $account = ConnectedAccount::findOrFail($request->account_id);

        if (!$account->onboarded || !$account->payouts_enabled) {
            return response()->json(['error' => 'Account is not ready to receive transfers'], 400);
        }

        $amountCents = intval($request->amount * 100);

        $result = $this->stripeConnect->transferAndPayout(
            $account->stripe_account_id,
            $amountCents,
            'usd',
            [
                'description' => $request->description ?? 'Car rental payment',
                'customer_name' => $account->full_name,
                'rental_id' => $request->rental_id ?? null,
            ]
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        Log::info('Transfer Result:', $result);

        $transferRecord = TransferRecord::create([
            'stripe_transfer_id' => $result['transfer_id'],
            'connected_account_id' => $account->id,
            'amount_cents' => $amountCents,
            'currency' => 'usd',
            'status' => $result['transfer']->status ?? 'pending',
            'description' => $request->description ?? 'Car rental payment',
            'transferred_at' => now(),
            'metadata' => [
                'payout_id' => $result['payout_id'] ?? null,
                'payout_error' => $result['payout_error'] ?? null,
                'rental_id' => $request->rental_id ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'transfer_id' => $transferRecord->id,
            'payout_id' => $result['payout_id'] ?? null,
            'amount' => $request->amount,
            'bank_transfer' => $result['payout_id'] ? 'Instant payout to bank successful' : 'Transfer to Stripe balance only',
        ]);
    }

    public function getTransfers()
    {
        $transfers = TransferRecord::with('connectedAccount')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($transfers);
    }

    public function carOwnerDashboard(Request $request)
    {
        $account = ConnectedAccount::findOrFail($request->account_id);

        if (!$account->onboarded) {
            return redirect('/payout-demo')->with('error', 'Account not fully onboarded yet');
        }

        $result = $this->stripeConnect->createLoginLink($account->stripe_account_id);

        if ($result['success']) {
            return redirect($result['url']);
        }

        return redirect('/payout-demo')->with('error', 'Failed to create dashboard link');
    }

    public function dashboardRefresh(Request $request)
    {
        return redirect('/payout-demo')->with('info', 'Dashboard session refreshed');
    }

    public function getBankAccounts(Request $request, $account_id)
    {
        $account = ConnectedAccount::findOrFail($account_id);
        // dd($account);
        $result = $this->stripeConnect->getExternalAccounts($account->stripe_account_id);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'bank_accounts' => array_map(function ($account) {
                    dd($account);
                    return [
                        'id' => $account->id,
                        'name' => $account->first_name,
                        'bank_name' => $account->bank_name ?? 'Unknown Bank',
                        'account_holder_name' => $account->account_holder_name === null ? 'Unknown Holder' : $account->account_holder_name,
                        'fingerprint' => $account->fingerprint ?? 'Unknown Fingerprint',
                        'last4' => $account->last4,
                        'currency' => $account->currency,
                        'default_for_currency' => $account->default_for_currency ?? false,
                    ];
                }, $result['accounts']),
                'default_account' => $result['default_account'],
            ]);
        }

        return response()->json(['success' => false, 'error' => $result['error']], 400);
    }
}
