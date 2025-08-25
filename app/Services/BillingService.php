<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\BillingInvoice;
use App\Models\InvoiceItem;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Process weekly billing for all active rentals
     */
    public function processWeeklyBilling(): array
    {
        $results = ['processed' => 0, 'failed' => 0, 'errors' => []];

        $rentalsToBill = Rental::where('status', Rental::STATUS_ACTIVE)
            ->where('next_billing_date', '<=', now())
            ->get();

        foreach ($rentalsToBill as $rental) {
            try {
                $this->processRentalBilling($rental);
                $results['processed']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = ['rental_id' => $rental->id, 'error' => $e->getMessage()];
                Log::error('Billing failed for rental', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Process billing for a specific rental
     */
    public function processRentalBilling(Rental $rental): BillingInvoice
    {
        return DB::transaction(function () use ($rental) {
            $billingPeriodStart = $rental->last_billed_date ?? $rental->start_date;
            $billingPeriodEnd = $this->calculateBillingPeriodEnd($rental, $billingPeriodStart);
            $billingData = $this->calculateBillingAmounts($rental, $billingPeriodStart, $billingPeriodEnd);

            $invoice = BillingInvoice::create([
                'rental_id' => $rental->id,
                'shop_owner_id' => $rental->shop_owner_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'billing_period_start' => $billingPeriodStart,
                'billing_period_end' => $billingPeriodEnd,
                'subtotal' => $billingData['subtotal'],
                'tax_amount' => $billingData['tax_amount'],
                'total_amount' => $billingData['total_amount'],
                'status' => BillingInvoice::STATUS_PENDING,
                'due_date' => $billingPeriodEnd->addDays(7),
                'billing_cycle' => $rental->billing_cycle,
            ]);

            $this->createInvoiceItems($invoice, $billingData);
            $this->processInvoicePayment($invoice);

            $rental->update([
                'last_billed_date' => $billingPeriodEnd,
                'next_billing_date' => $this->calculateNextBillingDate($billingPeriodEnd),
            ]);

            return $invoice;
        });
    }

    protected function calculateBillingPeriodEnd(Rental $rental, Carbon $periodStart): Carbon
    {
        $periodEnd = $periodStart->copy()->addWeek()->subDay();
        return $periodEnd > $rental->end_date ? $rental->end_date : $periodEnd;
    }

    protected function calculateBillingAmounts(Rental $rental, Carbon $periodStart, Carbon $periodEnd): array
    {
        $daysInPeriod = $periodStart->diffInDays($periodEnd) + 1;
        $baseRentalAmount = $rental->daily_rate * $daysInPeriod;
        $rapAmount = $this->calculateRapAmountForPeriod($rental, $periodStart, $periodEnd);
        $incidentAmount = $this->calculateIncidentAmountForPeriod($rental, $periodStart, $periodEnd);

        $subtotal = $baseRentalAmount + $rapAmount + $incidentAmount;
        $taxAmount = round($subtotal * 0.085, 2); // 8.5% tax
        $totalAmount = $subtotal + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_rental' => $baseRentalAmount,
            'rap_amount' => $rapAmount,
            'incident_amount' => $incidentAmount,
            'days_in_period' => $daysInPeriod,
        ];
    }

    protected function calculateRapAmountForPeriod(Rental $rental, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($rental->rap_days <= 0) return 0;

        $rapStartDate = max($periodStart, $rental->start_date);
        $rapEndDate = min($periodEnd, $rental->end_date);

        if ($rapStartDate > $rapEndDate) return 0;

        $rapDaysInPeriod = $rapStartDate->diffInDays($rapEndDate) + 1;
        $rapDaysToCharge = min($rapDaysInPeriod, $rental->rap_days);

        return $rental->rap_daily_rate * $rapDaysToCharge;
    }

    protected function calculateIncidentAmountForPeriod(Rental $rental, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $rental->incidents()
            ->whereBetween('incident_date', [$periodStart, $periodEnd])
            ->where('status', 'approved')
            ->sum('amount');
    }

    protected function createInvoiceItems(BillingInvoice $invoice, array $billingData): void
    {
        if ($billingData['base_rental'] > 0) {
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Rental Fee',
                'quantity' => $billingData['days_in_period'],
                'unit_price' => $invoice->rental->daily_rate,
                'total_price' => $billingData['base_rental'],
                'item_type' => InvoiceItem::ITEM_TYPE_RENTAL,
                'rental_days' => $billingData['days_in_period'],
                'daily_rate' => $invoice->rental->daily_rate,
            ]);
        }

        if ($billingData['rap_amount'] > 0) {
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Roadside Assistance Package (RAP)',
                'quantity' => 1,
                'unit_price' => $billingData['rap_amount'],
                'total_price' => $billingData['rap_amount'],
                'item_type' => InvoiceItem::ITEM_TYPE_RAP,
            ]);
        }

        if ($billingData['incident_amount'] > 0) {
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Incident Charges',
                'quantity' => 1,
                'unit_price' => $billingData['incident_amount'],
                'total_price' => $billingData['incident_amount'],
                'item_type' => InvoiceItem::ITEM_TYPE_FEE,
            ]);
        }

        if ($billingData['tax_amount'] > 0) {
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Tax',
                'quantity' => 1,
                'unit_price' => $billingData['tax_amount'],
                'total_price' => $billingData['tax_amount'],
                'item_type' => InvoiceItem::ITEM_TYPE_TAX,
            ]);
        }
    }

    protected function processInvoicePayment(BillingInvoice $invoice): void
    {
        try {
            $stripeInvoice = $this->stripeService->createInvoice($invoice);
            $invoice->update(['stripe_invoice_id' => $stripeInvoice->id]);

            $paymentIntent = $this->stripeService->collectPayment($invoice);
            $invoice->update(['stripe_payment_intent_id' => $paymentIntent->id]);

            if ($paymentIntent->status === 'succeeded') {
                $invoice->markAsPaid();
            }
        } catch (\Exception $e) {
            $invoice->markAsFailed();
            throw $e;
        }
    }

    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = now()->format('Y');
        $month = now()->format('m');
        $sequence = BillingInvoice::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('%s-%s-%s-%04d', $prefix, $year, $month, $sequence);
    }

    protected function calculateNextBillingDate(Carbon $fromDate): Carbon
    {
        return $fromDate->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Process early return for a rental
     */
    public function processEarlyReturn(Rental $rental, Carbon $returnDate): BillingInvoice
    {
        return DB::transaction(function () use ($rental, $returnDate) {
            // Calculate refund amount for unused days
            $unusedDays = $returnDate->diffInDays($rental->end_date);
            $refundAmount = $unusedDays * $rental->daily_rate;

            // Create adjustment invoice
            $invoice = BillingInvoice::create([
                'rental_id' => $rental->id,
                'shop_owner_id' => $rental->shop_owner_id,
                'invoice_number' => $this->generateInvoiceNumber() . '-ADJ',
                'billing_period_start' => $returnDate->addDay(),
                'billing_period_end' => $rental->end_date,
                'subtotal' => -$refundAmount,
                'tax_amount' => 0,
                'total_amount' => -$refundAmount,
                'status' => BillingInvoice::STATUS_PENDING,
                'due_date' => now(),
                'billing_cycle' => 'adjustment',
                'notes' => 'Early return adjustment - refund for unused days',
            ]);

            // Create invoice item for the adjustment
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Early Return Adjustment',
                'quantity' => $unusedDays,
                'unit_price' => -$rental->daily_rate,
                'total_price' => -$refundAmount,
                'item_type' => InvoiceItem::ITEM_TYPE_EARLY_RETURN,
                'rental_days' => $unusedDays,
                'daily_rate' => $rental->daily_rate,
            ]);

            // Update rental status
            $rental->update([
                'actual_return_date' => $returnDate,
                'end_date' => $returnDate,
                'status' => Rental::STATUS_COMPLETED,
                'early_return_days' => $unusedDays,
            ]);

            // Process refund
            $this->processRefund($invoice);

            return $invoice;
        });
    }

    /**
     * Process rental extension
     */
    public function processExtension(Rental $rental, int $additionalDays): BillingInvoice
    {
        return DB::transaction(function () use ($rental, $additionalDays) {
            $extensionStartDate = $rental->end_date->copy()->addDay();
            $extensionEndDate = $extensionStartDate->copy()->addDays($additionalDays - 1);
            $extensionAmount = $additionalDays * $rental->daily_rate;

            // Create extension invoice
            $invoice = BillingInvoice::create([
                'rental_id' => $rental->id,
                'shop_owner_id' => $rental->shop_owner_id,
                'invoice_number' => $this->generateInvoiceNumber() . '-EXT',
                'billing_period_start' => $extensionStartDate,
                'billing_period_end' => $extensionEndDate,
                'subtotal' => $extensionAmount,
                'tax_amount' => round($extensionAmount * 0.085, 2),
                'total_amount' => $extensionAmount + round($extensionAmount * 0.085, 2),
                'status' => BillingInvoice::STATUS_PENDING,
                'due_date' => now()->addDays(7),
                'billing_cycle' => 'extension',
                'notes' => "Rental extension for {$additionalDays} additional days",
            ]);

            // Create invoice items
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Rental Extension',
                'quantity' => $additionalDays,
                'unit_price' => $rental->daily_rate,
                'total_price' => $extensionAmount,
                'item_type' => InvoiceItem::ITEM_TYPE_EXTENSION,
                'rental_days' => $additionalDays,
                'daily_rate' => $rental->daily_rate,
            ]);

            if ($invoice->tax_amount > 0) {
                InvoiceItem::create([
                    'billing_invoice_id' => $invoice->id,
                    'description' => 'Tax on Extension',
                    'quantity' => 1,
                    'unit_price' => $invoice->tax_amount,
                    'total_price' => $invoice->tax_amount,
                    'item_type' => InvoiceItem::ITEM_TYPE_TAX,
                ]);
            }

            // Update rental
            $rental->update([
                'end_date' => $extensionEndDate,
                'extension_days' => ($rental->extension_days ?? 0) + $additionalDays,
                'next_billing_date' => $this->calculateNextBillingDate($extensionEndDate),
            ]);

            // Process payment
            $this->processInvoicePayment($invoice);

            return $invoice;
        });
    }

    /**
     * Process rental cancellation
     */
    public function processCancellation(Rental $rental, string $reason): BillingInvoice
    {
        return DB::transaction(function () use ($rental, $reason) {
            // Calculate cancellation fee (e.g., 1 day rental rate)
            $cancellationFee = $rental->daily_rate;
            $taxAmount = round($cancellationFee * 0.085, 2);

            // Create cancellation invoice
            $invoice = BillingInvoice::create([
                'rental_id' => $rental->id,
                'shop_owner_id' => $rental->shop_owner_id,
                'invoice_number' => $this->generateInvoiceNumber() . '-CAN',
                'billing_period_start' => $rental->start_date,
                'billing_period_end' => $rental->start_date,
                'subtotal' => $cancellationFee,
                'tax_amount' => $taxAmount,
                'total_amount' => $cancellationFee + $taxAmount,
                'status' => BillingInvoice::STATUS_PENDING,
                'due_date' => now()->addDays(7),
                'billing_cycle' => 'cancellation',
                'notes' => "Cancellation fee - {$reason}",
            ]);

            // Create invoice items
            InvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'description' => 'Cancellation Fee',
                'quantity' => 1,
                'unit_price' => $cancellationFee,
                'total_price' => $cancellationFee,
                'item_type' => InvoiceItem::ITEM_TYPE_CANCELLATION,
            ]);

            if ($taxAmount > 0) {
                InvoiceItem::create([
                    'billing_invoice_id' => $invoice->id,
                    'description' => 'Tax on Cancellation Fee',
                    'quantity' => 1,
                    'unit_price' => $taxAmount,
                    'total_price' => $taxAmount,
                    'item_type' => InvoiceItem::ITEM_TYPE_TAX,
                ]);
            }

            // Update rental status
            $rental->update([
                'status' => Rental::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Process payment
            $this->processInvoicePayment($invoice);

            return $invoice;
        });
    }

    /**
     * Process refund for an invoice
     */
    public function processRefund(BillingInvoice $invoice): void
    {
        try {
            if ($invoice->total_amount < 0) {
                // This is a credit/adjustment invoice, no refund needed
                $invoice->markAsPaid();
                return;
            }

            $refund = $this->stripeService->processRefund($invoice);

            if ($refund->status === 'succeeded') {
                $invoice->update([
                    'status' => BillingInvoice::STATUS_CANCELLED,
                    'notes' => ($invoice->notes ? $invoice->notes . ' ' : '') . 'Refund processed: ' . $refund->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Mark overdue invoices
     */
    public function markOverdueInvoices(): int
    {
        $overdueInvoices = BillingInvoice::where('status', BillingInvoice::STATUS_PENDING)
            ->where('due_date', '<', now())
            ->get();

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            $invoice->markAsOverdue();
            $count++;
        }

        return $count;
    }

    /**
     * Generate billing report
     */
    public function generateBillingReport(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = BillingInvoice::whereBetween('created_at', [$startDate, $endDate])
            ->with(['rental', 'invoiceItems'])
            ->get();

        $report = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_amount' => $invoices->sum('total_amount'),
                'paid_amount' => $invoices->where('status', BillingInvoice::STATUS_PAID)->sum('total_amount'),
                'pending_amount' => $invoices->where('status', BillingInvoice::STATUS_PENDING)->sum('total_amount'),
                'overdue_amount' => $invoices->where('status', BillingInvoice::STATUS_OVERDUE)->sum('total_amount'),
            ],
            'by_status' => $invoices->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('total_amount'),
                ];
            }),
            'by_type' => $invoices->groupBy('billing_cycle')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('total_amount'),
                ];
            }),
        ];

        return $report;
    }
}
