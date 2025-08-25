<?php

namespace App\Http\Controllers;

use App\Models\BillingInvoice;
use App\Models\Rental;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    protected $billingService;
    protected $stripeService;

    public function __construct(BillingService $billingService, StripeService $stripeService)
    {
        $this->middleware('auth');
        $this->billingService = $billingService;
        $this->stripeService = $stripeService;
    }

    public function dashboard()
    {
        $user = Auth::user();

        $activeRentals = Rental::where('customer_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->with(['vehicle', 'customer'])
            ->get();

        $recentInvoices = BillingInvoice::whereHas('rental', function ($query) use ($user) {
            $query->where('customer_id', $user->id);
        })
            ->with(['rental.vehicle', 'invoiceItems'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $outstandingBalance = $recentInvoices->where('status', '!=', 'paid')->sum('total_amount');
        $paidThisMonth = $recentInvoices->where('status', 'paid')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('total_amount');
        $dueSoon = $recentInvoices->where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays(7))
            ->sum('total_amount');
        $activeRentalsCount = $activeRentals->count();

        return view('billing.dashboard', compact(
            'activeRentals',
            'recentInvoices',
            'outstandingBalance',
            'paidThisMonth',
            'dueSoon',
            'activeRentalsCount'
        ));
    }

    public function indexInvoices()
    {
        $user = Auth::user();

        try {
            // Debug: Check if user exists
            Log::info('indexInvoices called', ['user_id' => $user->id]);

            // Check if there are any invoices at all
            $totalInvoices = BillingInvoice::count();
            Log::info('Total invoices in system', ['count' => $totalInvoices]);

            // Check if there are any rentals for this user
            $userRentals = Rental::where('customer_id', $user->id)->get();
            Log::info('User rentals', ['count' => $userRentals->count(), 'rental_ids' => $userRentals->pluck('id')]);

            $invoices = BillingInvoice::whereHas('rental', function ($query) use ($user) {
                $query->where('customer_id', $user->id);
            })
                ->with(['rental.vehicle', 'invoiceItems'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            Log::info('Invoices query result', ['count' => $invoices->count(), 'total' => $invoices->total()]);

            if (request()->expectsJson() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $invoices
                ]);
            }

            return view('billing.invoices', compact('invoices'));
        } catch (\Exception $e) {
            Log::error('Error in indexInvoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->expectsJson() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving invoices: ' . $e->getMessage()
                ], 500);
            }

            // For web requests, redirect with error
            return redirect()->route('billing.dashboard')->with('error', 'Error retrieving invoices: ' . $e->getMessage());
        }
    }

    public function showInvoice($id)
    {
        $user = Auth::user();

        $invoice = BillingInvoice::whereHas('rental', function ($query) use ($user) {
            $query->where('customer_id', $user->id);
        })
            ->with(['rental.vehicle', 'rental.customer', 'invoiceItems'])
            ->findOrFail($id);

        return view('billing.invoice', compact('invoice'));
    }

    public function showPaymentForm($id)
    {
        $user = Auth::user();

        $invoice = BillingInvoice::whereHas('rental', function ($query) use ($user) {
            $query->where('customer_id', $user->id);
        })
            ->with(['rental.vehicle', 'invoiceItems'])
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return redirect()->route('billing.invoice', $invoice->id)
                ->with('error', 'This invoice has already been paid.');
        }

        return view('billing.pay', compact('invoice'));
    }

    public function processPayment(Request $request, $id)
    {
        $user = Auth::user();

        $invoice = BillingInvoice::whereHas('rental', function ($query) use ($user) {
            $query->where('customer_id', $user->id);
        })
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return redirect()->route('billing.invoice', $invoice->id)
                ->with('error', 'This invoice has already been paid.');
        }

        $request->validate([
            'payment_method' => 'required|in:card,bank',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:10',
        ]);

        try {
            $paymentResult = $this->stripeService->collectPayment(
                $user,
                $invoice->total_amount,
                $request->all()
            );

            if ($paymentResult && $paymentResult->status === 'succeeded') {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                    'payment_method' => $request->payment_method,
                    'stripe_payment_intent_id' => $paymentResult->id ?? null,
                ]);

                return redirect()->route('billing.invoice', $invoice->id)
                    ->with('success', 'Payment processed successfully!');
            } else {
                throw new \Exception('Payment failed');
            }
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }

    public function showRental($id)
    {
        // dd($id);
        Log::info('Rental ID', ['id' => $id]);

        $user = Auth::user();

        Log::info('user id', ['user_id' => $user->id]);

        try {
            // First, let's check if the rental exists at all
            $rentalExists = Rental::find($id);
            Log::info('Rental exists check', ['rental_exists' => $rentalExists ? 'yes' : 'no', 'rental_data' => $rentalExists]);

            // Check all rentals for this user
            $userRentals = Rental::where('customer_id', $user->id)->get();
            Log::info('User rentals', ['count' => $userRentals->count(), 'rental_ids' => $userRentals->pluck('id')]);

            $rental = Rental::where('customer_id', $user->id)
                ->with(['vehicle', 'billingInvoices.invoiceItems'])
                ->findOrFail($id);

            Log::info('Rental', ['rental' => $rental]);

            if (request()->expectsJson() || request()->wantsJson()) {

                return response()->json([
                    'success' => true,
                    'data' => [
                        'rental' => $rental->load(['vehicle', 'billingInvoices.invoiceItems']),
                        'vehicle' => $rental->vehicle,
                        'invoices' => $rental->billingInvoices
                    ]
                ]);
            }

            return view('billing.rental', compact('rental'));
        } catch (\Exception $e) {
            Log::error('Error in showRental', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->expectsJson() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error retrieving rental: ' . $e->getMessage()
                ], 500);
            }

            // For web requests, redirect with error
            return redirect()->route('billing.dashboard')->with('error', 'Rental not found or access denied.');
        }
    }

    /**
     * API endpoint to get rental details
     */
    public function getRentalApi($id)
    {
        $user = Auth::user();

        try {
            $rental = Rental::where('customer_id', $user->id)
                ->with(['vehicle', 'billingInvoices.invoiceItems'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'rental' => $rental,
                    'vehicle' => $rental->vehicle,
                    'invoices' => $rental->billingInvoices
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found or access denied',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
