<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;

class MarkOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:mark-overdue {--dry-run : Show what would be marked without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark invoices as overdue based on due date';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService)
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Checking for overdue invoices...');

        try {
            if ($dryRun) {
                // Get overdue invoices without marking them
                $overdueInvoices = \App\Models\BillingInvoice::where('status', \App\Models\BillingInvoice::STATUS_PENDING)
                    ->where('due_date', '<', now())
                    ->with(['rental'])
                    ->get();

                if ($overdueInvoices->isEmpty()) {
                    $this->info('✅ No overdue invoices found.');
                    return 0;
                }

                $this->warn("Found {$overdueInvoices->count()} overdue invoice(s):");

                $rows = [];
                foreach ($overdueInvoices as $invoice) {
                    $daysOverdue = now()->diffInDays($invoice->due_date);
                    $rows[] = [
                        $invoice->id,
                        $invoice->invoice_number,
                        $invoice->rental->id ?? 'N/A',
                        $invoice->due_date->format('Y-m-d'),
                        $daysOverdue,
                        '$' . number_format($invoice->total_amount, 2),
                    ];
                }

                $this->table(
                    ['Invoice ID', 'Invoice #', 'Rental ID', 'Due Date', 'Days Overdue', 'Amount'],
                    $rows
                );

                $this->info("These invoices would be marked as overdue.");
            } else {
                $count = $billingService->markOverdueInvoices();

                if ($count > 0) {
                    $this->warn("⚠️ Marked {$count} invoice(s) as overdue.");
                } else {
                    $this->info('✅ No overdue invoices found.');
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to process overdue invoices: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
