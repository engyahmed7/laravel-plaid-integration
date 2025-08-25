<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;
use App\Models\Rental;
use Carbon\Carbon;

class ProcessBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:process {--rental-id= : Process billing for specific rental} {--force : Force billing even if not due}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process weekly billing for active rentals';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService)
    {
        $this->info('Starting billing process...');

        if ($rentalId = $this->option('rental-id')) {
            $rental = Rental::find($rentalId);
            if (!$rental) {
                $this->error("Rental with ID {$rentalId} not found.");
                return 1;
            }

            $this->info("Processing billing for rental #{$rental->id}");
            try {
                $invoice = $billingService->processRentalBilling($rental);
                $this->info("✅ Billing processed successfully!");
                $this->info("Invoice #{$invoice->invoice_number} created for $" . number_format($invoice->total_amount, 2));
            } catch (\Exception $e) {
                $this->error("❌ Billing failed: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('Processing weekly billing for all active rentals...');
            $results = $billingService->processWeeklyBilling();

            $this->info("Billing process completed:");
            $this->info("✅ Processed: {$results['processed']}");
            $this->info("❌ Failed: {$results['failed']}");

            if (!empty($results['errors'])) {
                $this->warn("Errors encountered:");
                foreach ($results['errors'] as $error) {
                    $this->warn("  Rental #{$error['rental_id']}: {$error['error']}");
                }
            }
        }

        $this->info('Billing process completed successfully!');
        return 0;
    }
}
