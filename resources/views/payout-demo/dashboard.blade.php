<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Car Rental Payout System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Car Rental Payout System</h1>
            <p class="text-gray-600">Instantly pay car owners directly to their bank accounts</p>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">${{ number_format($totalTransfers, 2) }}</div>
                    <div class="text-blue-800 text-sm">Total Transferred</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $accounts->where('onboarded', true)->count() }}
                    </div>
                    <div class="text-green-800 text-sm">Active Car Owners</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ $accounts->count() }}</div>
                    <div class="text-purple-800 text-sm">Total Car Owners</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Create Car Owner -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Add New Car Owner</h2>
                <form id="create-customer-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                        <select name="country"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="GB">United Kingdom</option>
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Add Car Owner & Setup Bank Account
                    </button>
                </form>
            </div>

            <!-- Make Rental Payment -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Pay Car Owner</h2>
                <form id="transfer-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Car Owner</label>
                        <select name="account_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Choose car owner...</option>
                            @foreach ($accounts->where('onboarded', true) as $account)
                                <option value="{{ $account->id }}">{{ $account->full_name }} ({{ $account->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Amount (USD)</label>
                        <input type="number" name="amount" step="0.01" min="0.50" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="e.g., 150.00">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rental Details</label>
                        <input type="text" name="description" placeholder="e.g., BMW rental - 3 days"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rental ID (Optional)</label>
                        <input type="text" name="rental_id" placeholder="e.g., RNT-2025-001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <button type="submit"
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Pay to Bank Account
                    </button>
                </form>
            </div>
        </div>

        <!-- Car Owners List -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold mb-4">Car Owners</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Car Owner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Transfers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Received</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Dashboard</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $account->full_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $account->country }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $account->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($account->onboarded && $account->payouts_enabled)
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @elseif($account->onboarded)
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                    @else
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Incomplete</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $account->transferRecords->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${{ number_format($account->transferRecords->sum('amount_cents') / 100, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($account->onboarded && $account->payouts_enabled)
                                        <a href="/payout-demo/car-owner-dashboard?account_id={{ $account->id }}"
                                            target="_blank"
                                            class="bg-blue-600 text-white px-3 py-1 rounded-md text-xs hover:bg-blue-700">
                                            View Dashboard
                                        </a>
                                    @else
                                        <span class="text-gray-400 text-xs">Setup Required</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold mb-4">Recent Payments</h2>
            <div id="recent-transfers" class="space-y-4">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Setup CSRF token for AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Create Customer Form
        $('#create-customer-form').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                email: $('input[name="email"]').val(),
                first_name: $('input[name="first_name"]').val(),
                last_name: $('input[name="last_name"]').val(),
                country: $('select[name="country"]').val()
            };

            $.ajax({
                url: '/payout-demo/customer',
                method: 'POST',
                data: formData,
                success: function(response) {
                    alert('Customer created! Redirecting to Stripe onboarding...');
                    window.open(response.onboard_url, '_blank');
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Failed to create customer';
                    alert('Error: ' + error);
                }
            });
        });

        // Transfer Form
        $('#transfer-form').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                account_id: $('select[name="account_id"]').val(),
                amount: parseFloat($('input[name="amount"]').val()),
                description: $('input[name="description"]').val(),
                rental_id: $('input[name="rental_id"]').val()
            };

            $.ajax({
                url: '/payout-demo/transfer',
                method: 'POST',
                data: formData,
                success: function(response) {
                    let message = `Payment of $${response.amount} sent successfully!\n`;
                    if (response.payout_id) {
                        message += `✅ Money sent directly to their bank account!`;
                    } else {
                        message += `⏳ Money in their Stripe balance (will auto-payout)`;
                    }
                    alert(message);
                    $('#transfer-form')[0].reset();
                    loadRecentTransfers();
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Failed to send transfer';
                    alert('Error: ' + error);
                }
            });
        });

        // Load recent transfers
        function loadRecentTransfers() {
            $.get('/payout-demo/transfers', function(transfers) {
                const container = $('#recent-transfers');
                container.empty();

                if (transfers.length === 0) {
                    container.html('<p class="text-gray-500">No transfers yet.</p>');
                    return;
                }

                transfers.slice(0, 10).forEach(function(transfer) {
                    const transferElement = `
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium">${transfer.connected_account.first_name} ${transfer.connected_account.last_name}</h3>
                                    <p class="text-sm text-gray-500">${transfer.connected_account.email}</p>
                                    <p class="text-sm text-gray-600 mt-1">${transfer.description || 'No description'}</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-semibold text-green-600">$${(transfer.amount_cents / 100).toFixed(2)}</div>
                                    <div class="text-sm text-gray-500">${transfer.currency.toUpperCase()}</div>
                                    <div class="text-xs text-gray-400 mt-1">${new Date(transfer.created_at).toLocaleDateString()}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    container.append(transferElement);
                });
            });
        }

        // Load transfers on page load
        $(document).ready(function() {
            loadRecentTransfers();
        });
    </script>
</body>

</html>
