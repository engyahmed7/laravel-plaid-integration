<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeService;
use App\Services\PlaidStripeIntegrationService;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    //
    protected StripeService $stripeService;
    protected PlaidStripeIntegrationService $integrationService;

    public function __construct(StripeService $stripeService, PlaidStripeIntegrationService $integrationService)
    {
        $this->middleware('auth');
        $this->stripeService = $stripeService;
        $this->integrationService = $integrationService;
    }

    public function createPaymentMethod(BankAccount $bankAccount)
    {
        $bankAccount->load('bankConnection.user');

        $this->authorize('update', $bankAccount->bankConnection);

        $result = $this->integrationService->createStripePaymentMethod($bankAccount);

        return response()->json($result);
    }

    public function createSetupIntent(Request $request)
    {
        $user = Auth::user();

        try {
            $bankConnection = BankConnection::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$bankConnection) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active bank connection found'
                ], 400);
            }

            $setupIntent = $this->stripeService->createSetupIntent($bankConnection->stripe_customer_id);

            return response()->json([
                'success' => true,
                'client_secret' => $setupIntent->client_secret
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create setup intent', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create setup intent'
            ], 500);
        }
    }

    public function verifyBankAccount(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount->bankConnection);

        $request->validate([
            'amounts' => 'required|array|size:2',
            'amounts.*' => 'required|integer|min:1|max:99'
        ]);

        try {
            $bankConnection = $bankAccount->bankConnection;

            $result = $this->stripeService->verifyBankAccount(
                $bankConnection->stripe_customer_id,
                $bankAccount->stripe_payment_method_id,
                $request->amounts
            );

            if ($result) {
                $bankAccount->update(['verification_status' => 'verified']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank account verified successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to verify bank account', [
                'bank_account_id' => $bankAccount->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to verify bank account'
            ], 500);
        }
    }

    public function dashboard()
    {
        $user = Auth::user();
        $bankConnections = BankConnection::where('user_id', $user->id)
            ->with(['accounts' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        $totalBalance = $bankConnections->flatMap->accounts->sum('balance_current');
        $accountsCount = $bankConnections->flatMap->accounts->count();

        return view('stripe.dashboard', [
            'bankConnections' => $bankConnections,
            'totalBalance' => $totalBalance,
            'accountsCount' => $accountsCount,
        ]);
    }
}
