@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Invoice #{{ $invoice->invoice_number }}</h1>
                    <p class="text-gray-600 mt-2">Generated on {{ $invoice->created_at->format('M d, Y') }}</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">${{ number_format($invoice->total_amount, 2) }}</div>
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full 
                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 
                           ($invoice->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Invoice Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Bill To</h3>
                        <div class="space-y-2">
                            <p class="text-sm text-gray-900">{{ $invoice->rental->user->name }}</p>
                            <p class="text-sm text-gray-600">{{ $invoice->rental->user->email }}</p>
                            @if($invoice->rental->user->stripe_customer_id)
                            <p class="text-sm text-gray-500">Customer ID: {{ $invoice->rental->user->stripe_customer_id }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Invoice Information</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Invoice Number:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Issue Date:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->created_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Due Date:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Status:</span>
                                <span class="text-sm font-medium text-gray-900">{{ ucfirst($invoice->status) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rental Information -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Rental Information</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Vehicle Details</h3>
                        <div class="space-y-2">
                            <p class="text-sm text-gray-900">{{ $invoice->rental->vehicle->year }} {{ $invoice->rental->vehicle->make }} {{ $invoice->rental->vehicle->model }}</p>
                            <p class="text-sm text-gray-600">License Plate: {{ $invoice->rental->vehicle->license_plate }}</p>
                            <p class="text-sm text-gray-600">VIN: {{ $invoice->rental->vehicle->vin }}</p>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Rental Period</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Start Date:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->rental->start_date->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">End Date:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $invoice->rental->end_date ? $invoice->rental->end_date->format('M d, Y') : 'Ongoing' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Daily Rate:</span>
                                <span class="text-sm font-medium text-gray-900">${{ number_format($invoice->rental->daily_rate, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Items -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Invoice Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @if($invoice->invoiceItems && $invoice->invoiceItems->count() > 0)
                            @foreach($invoice->invoiceItems as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item->description }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item->quantity }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${{ number_format($item->total_price, 2) }}</td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No items found for this invoice.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            
            <!-- Totals -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex justify-end">
                    <div class="w-64 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">${{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        @if($invoice->tax_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tax:</span>
                            <span class="font-medium">${{ number_format($invoice->tax_amount, 2) }}</span>
                        </div>
                        @endif
                        @if($invoice->discount_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-medium">-${{ number_format($invoice->discount_amount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                            <span>Total:</span>
                            <span>${{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center">
            <a href="{{ route('billing.dashboard') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                ← Back to Dashboard
            </a>
            
            @if($invoice->status !== 'paid')
            <a href="{{ route('billing.pay', $invoice->id) }}" class="bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200">
                Pay Now
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
