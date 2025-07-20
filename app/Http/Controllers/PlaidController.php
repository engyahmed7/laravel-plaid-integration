<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PlaidService;
use Illuminate\Support\Facades\Auth;

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

        return view('plaid.connect', [
            'link_token' => $tokenData['link_token'] ?? null
        ]);
    }

    public function exchangeToken(Request $request)
    {
        $response = $this->plaid->exchangePublicToken($request->public_token);

        Auth::user()->update([
            'plaid_access_token' => $response['access_token']
        ]);

        return redirect()->route('dashboard')->with('success', 'Bank account linked successfully!');
    }
}
