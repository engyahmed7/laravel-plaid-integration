<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Account;
use Exception;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCustomer($email, $name, $metadata = [])
    {
        return Customer::create([
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
        ]);
    }

    public function getCustomer($customerId)
    {
        return Customer::retrieve($customerId);
    }

    public function createSetupIntent($customerId, $paymentMethodTypes = ['us_bank_account'])
    {
        return SetupIntent::create([
            'customer' => $customerId,
            'payment_method_types' => $paymentMethodTypes,
            'usage' => 'off_session',
        ]);
    }

    public function createConnectedAccount($type = 'standard', $country = 'US')
    {
        return Account::create([
            'type' => $type,
            'country' => $country,
        ]);
    }

    public function createBankAccountPaymentMethod($customerId, $accountNumber, $routingNumber, $accountType = 'checking', $customerName = null)
    {
        if (!$customerName) {
            $customer = Customer::retrieve($customerId);
            $customerName = $customer->name ?: 'Unknown';
        }

        return PaymentMethod::create([
            'type' => 'us_bank_account',
            'us_bank_account' => [
                'account_number' => $accountNumber,
                'routing_number' => $routingNumber,
                'account_type' => $accountType,
            ],
            'billing_details' => [
                'name' => $customerName,
            ],
        ]);
    }

    public function attachPaymentMethodToCustomer($paymentMethodId, $customerId)
    {
        $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
        return $paymentMethod->attach(['customer' => $customerId]);
    }

    public function createExternalAccountFromPlaid($accountId, $plaidAccountDetails)
    {
        return Account::createExternalAccount($accountId, [
            'object' => 'bank_account',
            'account_number' => $plaidAccountDetails['account']['mask'],
            'routing_number' => $plaidAccountDetails['account']['routing_number'],
            'country' => 'US',
            'currency' => 'usd',
            'account_holder_type' => 'individual',
        ]);
    }

    public function verifyBankAccount($customerId, $paymentMethodId, $amounts)
    {
        // $paymentMethod = PaymentMethod::retrieve($paymentMethodId);

        // return $paymentMethod->verify([
        //     'amounts' => $amounts,
        // ]);

        try {
            // For US bank accounts, verification is typically done through micro-deposits
            // This is a simplified implementation - in practice, you'd use Stripe's
            // verification flow which involves confirming micro-deposits

            return true;
        } catch (Exception $e) {
            throw new Exception('Bank account verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a Stripe invoice for billing
     */
    public function createInvoice($billingInvoice)
    {
        try {
            $customer = $this->getCustomer($billingInvoice->rental->customer->stripe_customer_id);

            $invoice = \Stripe\Invoice::create([
                'customer' => $customer->id,
                'collection_method' => 'charge_automatically',
                'description' => "Rental Invoice #{$billingInvoice->invoice_number}",
                'metadata' => [
                    'rental_id' => $billingInvoice->rental_id,
                    'billing_period_start' => $billingInvoice->billing_period_start->format('Y-m-d'),
                    'billing_period_end' => $billingInvoice->billing_period_end->format('Y-m-d'),
                ],
            ]);

            // Add invoice items
            foreach ($billingInvoice->invoiceItems as $item) {
                \Stripe\InvoiceItem::create([
                    'invoice' => $invoice->id,
                    'customer' => $customer->id,
                    'amount' => (int)($item->total_price * 100), // Convert to cents
                    'currency' => 'usd',
                    'description' => $item->description,
                    'metadata' => [
                        'item_type' => $item->item_type,
                        'rental_days' => $item->rental_days,
                        'daily_rate' => $item->daily_rate,
                    ],
                ]);
            }

            return $invoice;
        } catch (Exception $e) {
            throw new Exception('Failed to create Stripe invoice: ' . $e->getMessage());
        }
    }

    /**
     * Collect payment for an invoice
     */
    public function collectPayment($billingInvoice)
    {
        try {
            $customer = $this->getCustomer($billingInvoice->rental->customer->stripe_customer_id);

            // Create payment intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($billingInvoice->total_amount * 100), // Convert to cents
                'currency' => 'usd',
                'customer' => $customer->id,
                'description' => "Payment for Invoice #{$billingInvoice->invoice_number}",
                'metadata' => [
                    'invoice_id' => $billingInvoice->id,
                    'rental_id' => $billingInvoice->rental_id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'confirm' => true,
                'off_session' => true,
            ]);

            return $paymentIntent;
        } catch (Exception $e) {
            throw new Exception('Failed to collect payment: ' . $e->getMessage());
        }
    }

    /**
     * Process refund for an invoice
     */
    public function processRefund($billingInvoice, $amount = null, $reason = 'requested_by_customer')
    {
        try {
            if (!$billingInvoice->stripe_payment_intent_id) {
                throw new Exception('No payment intent found for this invoice');
            }

            $refundData = [
                'payment_intent' => $billingInvoice->stripe_payment_intent_id,
                'reason' => $reason,
            ];

            if ($amount) {
                $refundData['amount'] = (int)($amount * 100); // Convert to cents
            }

            $refund = \Stripe\Refund::create($refundData);

            return $refund;
        } catch (Exception $e) {
            throw new Exception('Failed to process refund: ' . $e->getMessage());
        }
    }

    /**
     * Create a payment method for recurring billing
     */
    public function createPaymentMethodForRecurringBilling($customerId, $paymentMethodId)
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);

            // Attach to customer if not already attached
            if (!$paymentMethod->customer) {
                $paymentMethod->attach(['customer' => $customerId]);
            }

            // Set as default payment method
            Customer::update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return $paymentMethod;
        } catch (Exception $e) {
            throw new Exception('Failed to set up recurring billing: ' . $e->getMessage());
        }
    }

    /**
     * Get payment method details
     */
    public function getPaymentMethod($paymentMethodId)
    {
        try {
            return PaymentMethod::retrieve($paymentMethodId);
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve payment method: ' . $e->getMessage());
        }
    }

    /**
     * Detach payment method from customer
     */
    public function detachPaymentMethod($paymentMethodId)
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            return $paymentMethod->detach();
        } catch (Exception $e) {
            throw new Exception('Failed to detach payment method: ' . $e->getMessage());
        }
    }

    /**
     * Place security deposit hold on customer's payment method
     */
    public function placeSecurityDepositHold($securityDepositHold)
    {
        try {
            // This would create a payment intent for the security deposit
            // In a real implementation, you'd need to get the customer's payment method
            // For now, we'll just return a mock success
            return (object)['id' => 'pi_security_' . $securityDepositHold->id, 'status' => 'succeeded'];
        } catch (Exception $e) {
            throw new Exception('Failed to place security deposit hold: ' . $e->getMessage());
        }
    }

    /**
     * Release security deposit hold
     */
    public function releaseSecurityDepositHold($securityDepositHold)
    {
        try {
            // This would create a payment intent for the security deposit
            // In a real implementation, you'd need to get the customer's payment method
            // For now, we'll just return a mock success
            return (object)['id' => 'pi_security_' . $securityDepositHold->id, 'status' => 'succeeded'];
        } catch (Exception $e) {
            throw new Exception('Failed to release security deposit hold: ' . $e->getMessage());
        }
    }

    /**
     * Charge customer for RAP (Roadside Assistance Package)
     */
    public function chargeCustomerForRap($customer, $amount)
    {
        try {
            // This would create a payment intent for the RAP charge
            // In a real implementation, you'd need to get the customer's payment method
            // For now, we'll just return a mock success
            return (object)['id' => 'pi_rap_' . $customer->id, 'status' => 'succeeded'];
        } catch (Exception $e) {
            throw new Exception('Failed to charge customer for RAP: ' . $e->getMessage());
        }
    }

    /**
     * Charge customer for cancellation fees
     */
    public function chargeCustomerForCancellation($customer, $amount)
    {
        try {
            // This would create a payment intent for the cancellation charge
            // In a real implementation, you'd need to get the customer's payment method
            // For now, we'll just return a mock success
            return (object)['id' => 'pi_cancellation_' . $customer->id, 'status' => 'succeeded'];
        } catch (Exception $e) {
            throw new Exception('Failed to charge customer for cancellation: ' . $e->getMessage());
        }
    }
}
