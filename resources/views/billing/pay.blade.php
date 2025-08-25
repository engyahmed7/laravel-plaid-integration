@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Payment for Invoice #{{ $invoice->invoice_number }}</h1>
            <p class="text-gray-600 mt-2">Complete your payment to settle this invoice</p>
        </div>

        <!-- Invoice Summary -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Invoice Summary</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Invoice Number</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Due Date</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Amount</p>
                        <p class="text-2xl font-bold text-green-600">${{ number_format($invoice->total_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status</p>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 
                               ($invoice->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Invoice Items</h3>
                    <div class="space-y-3">
                        @if($invoice->invoiceItems && $invoice->invoiceItems->count() > 0)
                            @foreach($invoice->invoiceItems as $item)
                            <div class="flex justify-between items-center py-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $item->description }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->quantity }} x ${{ number_format($item->unit_price, 2) }}</p>
                                </div>
                                <p class="text-sm font-medium text-gray-900">${{ number_format($item->total_price, 2) }}</p>
                            </div>
                            @endforeach
                        @else
                            <p class="text-sm text-gray-500">No items found for this invoice.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Payment Information</h2>
            </div>
            <div class="p-6">
                <form id="payment-form" action="{{ route('billing.process-payment', $invoice->id) }}" method="POST">
                    @csrf
                    
                    <!-- Payment Method Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="card" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <span class="ml-3 text-sm text-gray-700">Credit/Debit Card</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="bank" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <span class="ml-3 text-sm text-gray-700">Bank Account (ACH)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Card Information -->
                    <div id="card-fields" class="space-y-4">
                        <div>
                            <label for="card_number" class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="expiry" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                <input type="text" id="expiry" name="expiry" placeholder="MM/YY" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="cvc" class="block text-sm font-medium text-gray-700 mb-2">CVC</label>
                                <input type="text" id="cvc" name="cvc" placeholder="123" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Account Information -->
                    <div id="bank-fields" class="space-y-4 hidden">
                        <div>
                            <label for="account_number" class="block text-sm font-medium text-gray-700 mb-2">Account Number</label>
                            <input type="text" id="account_number" name="account_number" placeholder="1234567890" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="routing_number" class="block text-sm font-medium text-gray-700 mb-2">Routing Number</label>
                            <input type="text" id="routing_number" name="routing_number" placeholder="123456789" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    <!-- Billing Address -->
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Billing Address</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="{{ auth()->user()->name }}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="{{ auth()->user()->name }}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <input type="text" id="address" name="address" placeholder="123 Main St" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 mt-4">
                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                <input type="text" id="city" name="city" placeholder="City" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="state" class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                <input type="text" id="state" name="state" placeholder="State" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="zip" class="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                                <input type="text" id="zip" name="zip" placeholder="12345" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-8">
                        <button type="submit" class="w-full bg-indigo-600 text-white py-3 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200">
                            Pay ${{ number_format($invoice->total_amount, 2) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
    const cardFields = document.getElementById('card-fields');
    const bankFields = document.getElementById('bank-fields');

    function toggleFields() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        if (selectedMethod === 'card') {
            cardFields.classList.remove('hidden');
            bankFields.classList.add('hidden');
        } else {
            cardFields.classList.add('hidden');
            bankFields.classList.remove('hidden');
        }
    }

    paymentMethodRadios.forEach(radio => {
        radio.addEventListener('change', toggleFields);
    });

    // Initialize on page load
    toggleFields();
});
</script>
@endsection
