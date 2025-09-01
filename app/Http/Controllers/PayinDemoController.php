<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeConnectService;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PayinDemoController extends Controller
{
    protected $stripeConnect;
    protected $stripe;

    public function __construct(StripeConnectService $stripeConnect)
    {
        $this->stripeConnect = $stripeConnect;
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function dashboard()
    {
        $payments = PaymentRecord::orderBy('created_at', 'desc')->limit(20)->get();
        $totalPayments = PaymentRecord::where('status', 'succeeded')->sum('amount_cents') / 100;
        $totalCount = PaymentRecord::where('status', 'succeeded')->count();

        return view('payin-demo.dashboard', compact('payments', 'totalPayments', 'totalCount'));
    }

    public function createCustomer(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        try {
            $customer = $this->stripe->customers->create([
                'email' => $request->email,
                'name' => $request->name,
                'phone' => $request->phone,
                'metadata' => [
                    'type' => 'car_rental_customer',
                    'created_via' => 'payin_demo',
                ],
            ]);

            return response()->json([
                'success' => true,
                'customer_id' => $customer->id,
                'message' => 'Customer created successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function createPaymentMethod(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|string',
            'payment_method_id' => 'required|string',
        ]);

        try {
            $this->stripe->paymentMethods->attach(
                $request->payment_method_id,
                ['customer' => $request->customer_id]
            );

            $pm = $this->stripe->paymentMethods->retrieve($request->payment_method_id);

            return response()->json([
                'success' => true,
                'payment_method_id' => $pm->id,
                'last4' => $pm->card->last4,
                'brand' => $pm->card->brand
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function getCustomerPaymentMethods(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|string',
        ]);

        try {
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $request->customer_id,
                'type' => 'card',
            ]);

            return response()->json([
                'success' => true,
                'payment_methods' => array_map(function ($pm) {
                    return [
                        'id' => $pm->id,
                        'brand' => $pm->card->brand,
                        'last4' => $pm->card->last4,
                        'exp_month' => $pm->card->exp_month,
                        'exp_year' => $pm->card->exp_year,
                        'name' => $pm->billing_details->name,
                    ];
                }, $paymentMethods->data),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|string',
            'payment_method_id' => 'required|string',
            'amount' => 'required|numeric|min:0.50',
            'description' => 'nullable|string|max:255',
            'rental_id' => 'nullable|string',
        ]);

        $amountCents = intval($request->amount * 100);

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => 'usd',
                'customer' => $request->customer_id,
                'payment_method' => $request->payment_method_id,
                'description' => $request->description ?? 'Car rental payment',
                'confirm' => true,
                'return_url' => url('/payin-demo'),
                'metadata' => [
                    'rental_id' => $request->rental_id ?? null,
                    'type' => 'car_rental',
                ],
            ]);

            $paymentRecord = PaymentRecord::create([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'customer_id' => $request->customer_id,
                'payment_method_id' => $request->payment_method_id,
                'amount_cents' => $amountCents,
                'currency' => 'usd',
                'status' => $paymentIntent->status,
                'description' => $request->description ?? 'Car rental payment',
                'metadata' => [
                    'rental_id' => $request->rental_id ?? null,
                    'application_fee' => intval($amountCents * 0.03),
                ],
            ]);

            return response()->json([
                'success' => true,
                'payment_id' => $paymentRecord->id,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $request->amount,
                'application_fee' => $amountCents * 0.03 / 100,
                'net_amount' => ($amountCents - intval($amountCents * 0.03)) / 100,
                'message' => 'Payment processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Payment processing error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function getPayments(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);

            $payments = PaymentRecord::with('customer')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function refundPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|in:duplicate,fraudulent,requested_by_customer',
        ]);

        Log::info($id);
        try {
            $paymentRecord = PaymentRecord::findOrFail($id);
            Log::info($paymentRecord);
            $request->amount = $paymentRecord->amount;
            Log::info('paymentRecord', ['amount' => $request->amount]);

            $refundAmount = $request->amount ?? 0;


            Log::info($paymentRecord->stripe_payment_intent_id);

            // $paymentIntent = $this->stripe->paymentIntents->retrieve(
            //     $paymentRecord->stripe_payment_intent_id,
            //     ['stripe_account' => $paymentRecord->stripe_account_id]
            // );

            $paymentIntent = $this->stripe->paymentIntents->retrieve(
                $paymentRecord->stripe_payment_intent_id,
            );

            // Log::info($paymentIntent);
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment is not captured yet, cannot refund.');
            }

            // Create refund
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $paymentRecord->stripe_payment_intent_id,
                'amount' => $refundAmount,
                'reason' => $request->reason ?? 'requested_by_customer',
                'metadata' => [
                    'payment_record_id' => $paymentRecord->id,
                ],
            ]);

            Log::info($refund);


            $paymentRecord->update([
                'status' => 'refunded',
                'metadata' => array_merge($paymentRecord->metadata ?? [], [
                    'refund_id' => $refund->id,
                    'refund_amount' => $refund->amount,
                    'refund_reason' => $refund->reason,
                ]),
            ]);

            return redirect()->back()->with('success', 'Payment refunded successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    function getAllTransactions(Request $request)
    {
        try {
            $transactions = $this->stripe->paymentIntents->all([
                'limit' => 100,
            ]);

            return response()->json([
                'success' => true,
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // function retrieveTransaction(Request $request, $id)
    // {
    //     try {
    //         // $transaction = $this->stripe->paymentIntents->retrieve($id);
    //         $transaction = $this->stripe->paymentIntents->all([
    //             'destination' => $accountId,
    //             'limit' => $limit,
    //         ]);
    //         return response()->json([
    //             'success' => true,
    //             'data' => $transaction,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ], 400);
    //     }
    // }

    function retrieveTransactionForCustomerId(Request $request, $id)
    {
        try {
            $transaction = $this->stripe->paymentIntents->retrieve($id);

            return response()->json([
                'success' => true,
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
