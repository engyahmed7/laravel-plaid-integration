<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;
use Carbon\Carbon;

class GenerateBillingReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:report {--start= : Start date (Y-m-d)} {--end= : End date (Y-m-d)} {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate billing report for a date range';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService)
    {
        $startDate = $this->option('start') ? Carbon::parse($this->option('start')) : Carbon::now()->startOfMonth();
        $endDate = $this->option('end') ? Carbon::parse($this->option('end')) : Carbon::now()->endOfMonth();
        $format = $this->option('format');

        $this->info("Generating billing report for {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        try {
            $report = $billingService->generateBillingReport($startDate, $endDate);

            if ($format === 'json') {
                $this->output->write(json_encode($report, JSON_PRETTY_PRINT));
            } elseif ($format === 'csv') {
                $this->outputCsv($report);
            } else {
                $this->outputTable($report);
            }
        } catch (\Exception $e) {
            $this->error("Failed to generate report: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function outputTable(array $report): void
    {
        $this->info("\n📊 Billing Report Summary");
        $this->info("Period: {$report['period']['start']} to {$report['period']['end']}");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Invoices', $report['summary']['total_invoices']],
                ['Total Amount', '$' . number_format($report['summary']['total_amount'], 2)],
                ['Paid Amount', '$' . number_format($report['summary']['paid_amount'], 2)],
                ['Pending Amount', '$' . number_format($report['summary']['pending_amount'], 2)],
                ['Overdue Amount', '$' . number_format($report['summary']['overdue_amount'], 2)],
            ]
        );

        if (!empty($report['by_status'])) {
            $this->info("\n📈 Breakdown by Status");
            $statusRows = [];
            foreach ($report['by_status'] as $status => $data) {
                $statusRows[] = [
                    ucfirst($status),
                    $data['count'],
                    '$' . number_format($data['total_amount'], 2)
                ];
            }

            $this->table(
                ['Status', 'Count', 'Total Amount'],
                $statusRows
            );
        }

        if (!empty($report['by_type'])) {
            $this->info("\n🏷️ Breakdown by Billing Type");
            $typeRows = [];
            foreach ($report['by_type'] as $type => $data) {
                $typeRows[] = [
                    ucfirst($type),
                    $data['count'],
                    '$' . number_format($data['total_amount'], 2)
                ];
            }

            $this->table(
                ['Type', 'Count', 'Total Amount'],
                $typeRows
            );
        }
    }

    protected function outputCsv(array $report): void
    {
        $this->output->write("Period Start,Period End,Total Invoices,Total Amount,Paid Amount,Pending Amount,Overdue Amount\n");
        $this->output->write("{$report['period']['start']},{$report['period']['end']},{$report['summary']['total_invoices']},{$report['summary']['total_amount']},{$report['summary']['paid_amount']},{$report['summary']['pending_amount']},{$report['summary']['overdue_amount']}\n");
    }
}
