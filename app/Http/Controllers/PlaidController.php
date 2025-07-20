<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PlaidService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlaidController extends Controller
{
    protected $plaid;

    public function __construct(PlaidService $plaid)
    {
        $this->middleware('auth');
        $this->plaid = $plaid;
    }

    public function showLinkPage()
    {
        $tokenData = $this->plaid->createLinkToken(Auth::id());
        Log::info('Link token created: ' . $tokenData['link_token']);

        return view('plaid.connect', [
            'link_token' => $tokenData['link_token'] ?? null
        ]);
    }

    public function exchangeToken(Request $request)
    {
        try {
            $request->validate([
                'public_token' => 'required|string'
            ]);

            $response = $this->plaid->exchangePublicToken($request->public_token);

            if (!isset($response['access_token'])) {
                Log::error('No access token in Plaid response', $response);
                return redirect()->route('plaid.connect')
                    ->with('error', 'Failed to link bank account. Please try again.');
            }

            Log::info('Public token exchanged: ' . $response['access_token']);

            Auth::user()->update([
                'plaid_access_token' => $response['access_token']
            ]);

            return response()->json([
                'redirect' => route('plaid.transactions'),
                'message' => 'Bank account linked successfully!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Token exchange failed: ' . $e->getMessage());
            return redirect()->route('plaid.connect')->with('error', 'Failed to link bank account. Please try again.');
        }
    }


    public function showTransactions()
    {
        $accessToken = Auth::user()->plaid_access_token;

        if (!$accessToken) {
            return redirect()->route('plaid.connect')->with('error', 'You need to link a bank account first.');
        }

        $startDate = Carbon::now()->subDays(30)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $transactions = $this->plaid->getTransactions($accessToken, $startDate, $endDate);

        return view('plaid.transactions', [
            'transactions' => $transactions['transactions'] ?? [],
        ]);
    }
}
