<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PlaidService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\PlaidStripeIntegrationService;
use App\Models\BankConnection;

class PlaidController extends Controller
{
    protected PlaidService $plaid;
    protected PlaidStripeIntegrationService $integrationService;
    public function __construct(PlaidService $plaid, PlaidStripeIntegrationService $integrationService)
    {
        $this->middleware('auth');
        $this->plaid = $plaid;
        $this->integrationService = $integrationService;
    }

    public function showLinkPage()
    {
        $user = Auth::user();
        $result = $this->integrationService->initiateBankConnection($user);

        if (!$result['success']) {
            return redirect()->back()->with('error', 'Failed to initialize bank connection: ' . $result['error']);
        }

        return view('plaid.connect', [
            'link_token' => $result['link_token']
        ]);
    }

    public function exchangeToken(Request $request)
    {
        try {
            $request->validate([
                'public_token' => 'required|string',
                'metadata' => 'nullable|array'
            ]);

            $user = Auth::user();
            $result = $this->integrationService->completeBankConnection(
                $user,
                $request->public_token,
                $request->metadata ?? []
            );


            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }


            return response()->json([
                'success' => true,
                'redirect' => route('dashboard'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to link bank account. Please try again.'
            ], 500);
        }
    }


    public function showTransactions()
    {
        $user = Auth::user();
        $bankConnections = BankConnection::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['accounts', 'transactions' => function ($query) {
                $query->orderBy('transaction_date', 'desc')->limit(50);
            }])
            ->get();


        if ($bankConnections->isEmpty()) {
            return redirect()->route('plaid.connect')->with('error', 'You need to link a bank account first.');
        }

        // $startDate = Carbon::now()->subDays(30)->toDateString();
        // $endDate = Carbon::now()->toDateString();

        // $transactions = $this->plaid->getTransactions($accessToken, $startDate, $endDate);

        return view('plaid.transactions', [
            'bankConnections' => $bankConnections,
        ]);
    }

    public function syncTransactions(Request $request, BankConnection $bankConnection)
    {
        $this->authorize('update', $bankConnection);

        $startDate = $request->has('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->has('end_date') ? Carbon::parse($request->end_date) : null;

        $result = $this->integrationService->syncTransactions($bankConnection, $startDate, $endDate);

        return response()->json($result);
    }

    public function syncAccounts(BankConnection $bankConnection)
    {
        $this->authorize('update', $bankConnection);

        $result = $this->integrationService->syncBankAccounts($bankConnection);

        return response()->json($result);
    }
}
