<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BillingService;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\BillingInvoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $billingService;
    protected $shopOwner;
    protected $customer;
    protected $carOwner;
    protected $vehicle;
    protected $rental;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Stripe service to avoid actual API calls
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->andReturn((object)['id' => 'in_test_123']);
            $mock->shouldReceive('collectPayment')->andReturn((object)['id' => 'pi_test_123', 'status' => 'succeeded']);
        });

        $this->billingService = app(BillingService::class);

        // Create test users
        $this->shopOwner = User::factory()->create([
            'stripe_customer_id' => 'cus_test_shop_owner'
        ]);

        $this->customer = User::factory()->create([
            'stripe_customer_id' => 'cus_test_customer'
        ]);

        $this->carOwner = User::factory()->create([
            'stripe_customer_id' => 'cus_test_car_owner'
        ]);

        // Create test vehicle
        $this->vehicle = Vehicle::create([
            'car_owner_id' => $this->carOwner->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'license_plate' => 'TEST123',
            'vin' => '1HGBH41JXMN109186',
            'vehicle_type' => 'standard',
            'daily_rate' => 50.00,
            'rap_daily_rate' => 10.00,
            'status' => 'available',
        ]);

        // Create test rental
        $this->rental = Rental::create([
            'shop_owner_id' => $this->shopOwner->id,
            'customer_id' => $this->customer->id,
            'car_owner_id' => $this->carOwner->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => Carbon::now()->subWeek(),
            'end_date' => Carbon::now()->addWeeks(2),
            'daily_rate' => 50.00,
            'total_amount' => 50.00 * 21, // 21 days
            'rap_daily_rate' => 10.00,
            'rap_days' => 7,
            'rap_total' => 10.00 * 7,
            'status' => Rental::STATUS_ACTIVE,
            'billing_cycle' => Rental::BILLING_CYCLE_WEEKLY,
            'next_billing_date' => Carbon::now(),
            'commission_rate' => 0.15,
            'commission_amount' => (50.00 * 21) * 0.15,
            'payout_amount' => (50.00 * 21) * 0.85,
        ]);
    }

    /** @test */
    public function it_can_generate_invoice_number()
    {
        // Test that invoice numbers are generated correctly by creating an invoice
        // The generateInvoiceNumber method is protected, so we test it indirectly
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->andReturn((object)['id' => 'in_test_123']);
            $mock->shouldReceive('collectPayment')->andReturn((object)['id' => 'pi_test_123', 'status' => 'succeeded']);
        });

        $invoice = $this->billingService->processRentalBilling($this->rental);

        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{2}-\d{4}$/', $invoice->invoice_number);
    }

    /** @test */
    public function it_can_process_rental_billing()
    {
        // Mock the Stripe service to avoid actual API calls
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->andReturn((object)['id' => 'in_test_123']);
            $mock->shouldReceive('collectPayment')->andReturn((object)['id' => 'pi_test_123', 'status' => 'succeeded']);
        });

        $invoice = $this->billingService->processRentalBilling($this->rental);

        $this->assertInstanceOf(BillingInvoice::class, $invoice);
        $this->assertEquals($this->rental->id, $invoice->rental_id);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertGreaterThan(0, $invoice->total_amount);
    }

    /** @test */
    public function it_can_process_weekly_billing()
    {
        // Mock the Stripe service
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->andReturn((object)['id' => 'in_test_123']);
            $mock->shouldReceive('collectPayment')->andReturn((object)['id' => 'pi_test_123', 'status' => 'succeeded']);
        });

        $results = $this->billingService->processWeeklyBilling();

        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('failed', $results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertIsArray($results['errors']);
    }

    /** @test */
    public function it_can_generate_billing_report()
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $report = $this->billingService->generateBillingReport($startDate, $endDate);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('by_status', $report);
        $this->assertArrayHasKey('by_type', $report);

        $this->assertEquals($startDate->format('Y-m-d'), $report['period']['start']);
        $this->assertEquals($endDate->format('Y-m-d'), $report['period']['end']);
    }

    /** @test */
    public function it_can_mark_overdue_invoices()
    {
        // Create an overdue invoice
        $overdueInvoice = BillingInvoice::create([
            'rental_id' => $this->rental->id,
            'shop_owner_id' => $this->shopOwner->id,
            'invoice_number' => 'INV-TEST-001',
            'billing_period_start' => Carbon::now()->subWeeks(2),
            'billing_period_end' => Carbon::now()->subWeek(),
            'subtotal' => 100.00,
            'tax_amount' => 8.50,
            'total_amount' => 108.50,
            'status' => BillingInvoice::STATUS_PENDING,
            'due_date' => Carbon::now()->subDays(5),
            'billing_cycle' => 'weekly',
        ]);

        $count = $this->billingService->markOverdueInvoices();

        $this->assertEquals(1, $count);

        $overdueInvoice->refresh();
        $this->assertEquals(BillingInvoice::STATUS_OVERDUE, $overdueInvoice->status);
    }
}
