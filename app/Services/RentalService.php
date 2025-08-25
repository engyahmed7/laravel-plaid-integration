<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\SecurityDepositHold;
use App\Services\StripeService;
use App\Services\PlaidService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RentalService
{
    protected $stripeService;
    protected $plaidService;

    public function __construct(StripeService $stripeService, PlaidService $plaidService)
    {
        $this->stripeService = $stripeService;
        $this->plaidService = $plaidService;
    }

    /**
     * Create a new rental booking
     */
    public function createRental(array $data): Rental
    {
        return DB::transaction(function () use ($data) {
            // Validate vehicle availability
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            if (!$vehicle->isAvailable()) {
                throw new \Exception('Vehicle is not available for the selected dates');
            }

            // Calculate rental details
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Use RNTLS standard rates
            $dailyRate = $vehicle->getStandardDailyRateAttribute();
            $rapDailyRate = $vehicle->getStandardRapDailyRateAttribute();

            // Create the rental
            $rental = Rental::create([
                'shop_owner_id' => $data['shop_owner_id'],
                'customer_id' => $data['customer_id'],
                'car_owner_id' => $vehicle->car_owner_id,
                'vehicle_id' => $vehicle->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'daily_rate' => $dailyRate,
                'total_amount' => $dailyRate * $totalDays,
                'rap_daily_rate' => $rapDailyRate,
                'rap_days' => 0,
                'rap_total' => 0,
                'status' => Rental::STATUS_PENDING,
                'billing_cycle' => Rental::BILLING_CYCLE_WEEKLY,
                'next_billing_date' => $this->calculateNextBillingDate($startDate),
                'commission_rate' => 0.15, // 15% default commission
                'commission_amount' => ($dailyRate * $totalDays) * 0.15,
                'payout_amount' => ($dailyRate * $totalDays) * 0.85,
            ]);

            // Update vehicle status
            $vehicle->update(['status' => Vehicle::STATUS_RENTED]);

            // Create security deposit hold
            $this->createSecurityDepositHold($rental, $data['customer_id']);

            Log::info('Rental created', ['rental_id' => $rental->id, 'vehicle_id' => $vehicle->id]);

            return $rental;
        });
    }

    /**
     * Add RAP (Roadside Assistance Package) to rental
     */
    public function addRapToRental(Rental $rental, int $rapDays): bool
    {
        return DB::transaction(function () use ($rental, $rapDays) {
            if (!$rental->isPending() && !$rental->isActive()) {
                throw new \Exception('Cannot add RAP to rental in current status');
            }

            $rapTotal = $rental->rap_daily_rate * $rapDays;

            $rental->update([
                'rap_days' => $rapDays,
                'rap_total' => $rapTotal,
            ]);

            // Charge customer for RAP
            $this->chargeCustomerForRap($rental, $rapTotal);

            Log::info('RAP added to rental', [
                'rental_id' => $rental->id,
                'rap_days' => $rapDays,
                'rap_total' => $rapTotal
            ]);

            return true;
        });
    }

    /**
     * Activate rental (when customer picks up vehicle)
     */
    public function activateRental(Rental $rental): bool
    {
        return DB::transaction(function () use ($rental) {
            if (!$rental->isPending()) {
                throw new \Exception('Rental must be in pending status to activate');
            }

            // Verify security deposit hold is active
            $securityDepositHold = SecurityDepositHold::where('rental_id', $rental->id)->first();
            if (!$securityDepositHold || !$securityDepositHold->isActive()) {
                throw new \Exception('Security deposit hold must be active to activate rental');
            }

            $rental->update([
                'status' => Rental::STATUS_ACTIVE,
                'next_billing_date' => $this->calculateNextBillingDate(now()),
            ]);

            Log::info('Rental activated', ['rental_id' => $rental->id]);

            return true;
        });
    }

    /**
     * Complete rental (when customer returns vehicle)
     */
    public function completeRental(Rental $rental, ?Carbon $actualReturnDate = null): bool
    {
        return DB::transaction(function () use ($rental, $actualReturnDate) {
            if (!$rental->isActive()) {
                throw new \Exception('Rental must be active to complete');
            }

            $actualReturnDate = $actualReturnDate ?? now();
            $actualDays = $rental->start_date->diffInDays($actualReturnDate) + 1;
            $originalDays = $rental->getTotalDaysAttribute();

            // Calculate adjustments
            $extensionDays = max(0, $actualDays - $originalDays);
            $earlyReturnDays = max(0, $originalDays - $actualDays);

            $rental->update([
                'status' => Rental::STATUS_COMPLETED,
                'actual_return_date' => $actualReturnDate,
                'extension_days' => $extensionDays,
                'early_return_days' => $earlyReturnDays,
                'payout_status' => Rental::PAYOUT_STATUS_PENDING,
            ]);

            // Update vehicle status
            $rental->vehicle->update(['status' => Vehicle::STATUS_AVAILABLE]);

            // Release security deposit hold
            $this->releaseSecurityDepositHold($rental);

            // Process final billing adjustments
            $this->processFinalBillingAdjustments($rental);

            // Schedule payout to car owner
            $this->scheduleCarOwnerPayout($rental);

            Log::info('Rental completed', [
                'rental_id' => $rental->id,
                'actual_days' => $actualDays,
                'extension_days' => $extensionDays,
                'early_return_days' => $earlyReturnDays
            ]);

            return true;
        });
    }

    /**
     * Cancel rental
     */
    public function cancelRental(Rental $rental, string $reason): bool
    {
        return DB::transaction(function () use ($rental, $reason) {
            if (!$rental->isPending()) {
                throw new \Exception('Only pending rentals can be cancelled');
            }

            $cancellationDate = now();
            $startDate = $rental->start_date;
            $daysUntilStart = $cancellationDate->diffInDays($startDate, false);

            // Apply cancellation policy (min 1-day charge if cancelled <24h before start)
            $cancellationCharge = 0;
            if ($daysUntilStart < 1) {
                $cancellationCharge = $rental->daily_rate;
            }

            $rental->update([
                'status' => Rental::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => $cancellationDate,
            ]);

            // Update vehicle status
            $rental->vehicle->update(['status' => Vehicle::STATUS_AVAILABLE]);

            // Release security deposit hold
            $this->releaseSecurityDepositHold($rental);

            // Process cancellation charges if applicable
            if ($cancellationCharge > 0) {
                $this->processCancellationCharges($rental, $cancellationCharge);
            }

            Log::info('Rental cancelled', [
                'rental_id' => $rental->id,
                'reason' => $reason,
                'cancellation_charge' => $cancellationCharge
            ]);

            return true;
        });
    }

    /**
     * Extend rental
     */
    public function extendRental(Rental $rental, int $additionalDays): bool
    {
        return DB::transaction(function () use ($rental, $additionalDays) {
            if (!$rental->isActive()) {
                throw new \Exception('Only active rentals can be extended');
            }

            $newEndDate = $rental->end_date->addDays($additionalDays);
            $extensionAmount = $rental->daily_rate * $additionalDays;

            $rental->update([
                'end_date' => $newEndDate,
                'extension_days' => $rental->extension_days + $additionalDays,
                'total_amount' => $rental->total_amount + $extensionAmount,
                'commission_amount' => $rental->commission_amount + ($extensionAmount * 0.15),
                'payout_amount' => $rental->payout_amount + ($extensionAmount * 0.85),
            ]);

            // Update next billing date
            $rental->update([
                'next_billing_date' => $this->calculateNextBillingDate($rental->next_billing_date),
            ]);

            Log::info('Rental extended', [
                'rental_id' => $rental->id,
                'additional_days' => $additionalDays,
                'extension_amount' => $extensionAmount
            ]);

            return true;
        });
    }

    /**
     * Calculate next billing date (weekly billing cycle)
     */
    protected function calculateNextBillingDate(Carbon $fromDate): Carbon
    {
        return $fromDate->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Create security deposit hold
     */
    protected function createSecurityDepositHold(Rental $rental, int $customerId): SecurityDepositHold
    {
        $hold = SecurityDepositHold::create([
            'rental_id' => $rental->id,
            'customer_id' => $customerId,
            'amount' => 250.00, // Standard $250 security deposit
            'status' => SecurityDepositHold::STATUS_PENDING,
        ]);

        // Place hold on customer's card via Stripe
        $this->stripeService->placeSecurityDepositHold($hold);

        return $hold;
    }

    /**
     * Release security deposit hold
     */
    protected function releaseSecurityDepositHold(Rental $rental): void
    {
        if ($rental->securityDepositHold && $rental->securityDepositHold->canBeReleased()) {
            $rental->securityDepositHold->releaseAmount(
                $rental->securityDepositHold->amount,
                SecurityDepositHold::RELEASE_REASON_RENTAL_COMPLETED
            );

            // Release hold via Stripe
            $this->stripeService->releaseSecurityDepositHold($rental->securityDepositHold);
        }
    }

    /**
     * Charge customer for RAP
     */
    protected function chargeCustomerForRap(Rental $rental, float $amount): void
    {
        // Charge customer's card via Stripe
        $this->stripeService->chargeCustomerForRap($rental->customer, $amount);
    }

    /**
     * Process final billing adjustments
     */
    protected function processFinalBillingAdjustments(Rental $rental): void
    {
        // This would integrate with the billing service to create final invoice
        // and handle any adjustments for extensions, early returns, etc.
    }

    /**
     * Process cancellation charges
     */
    protected function processCancellationCharges(Rental $rental, float $amount): void
    {
        // Charge customer for cancellation fees via Stripe
        $this->stripeService->chargeCustomerForCancellation($rental->customer, $amount);
    }

    /**
     * Schedule payout to car owner
     */
    protected function scheduleCarOwnerPayout(Rental $rental): void
    {
        // This would integrate with the payout service to schedule payout to car owner
        // via Plaid for the rental amount minus commission
    }
}
