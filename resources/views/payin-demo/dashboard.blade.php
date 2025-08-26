<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Car Rental Pay-In System</title>

    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }

        .toast {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            margin-bottom: 12px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.hide {
            transform: translateX(400px);
            opacity: 0;
        }

        /* Toast Types */
        .toast.success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }

        .toast.error {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }

        .toast.info {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }

        .toast.warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }

        .toast-icon {
            margin-right: 12px;
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 16px;
        }

        .toast-message {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            margin-left: 12px;
            padding: 4px;
            border-radius: 4px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .toast-close:hover {
            opacity: 1;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            width: 100%;
            transform-origin: left;
        }

        .demo-section {
            text-align: center;
            margin-top: 50px;
        }

        .demo-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            margin: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .demo-btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .toast-container {
                left: 20px;
                right: 20px;
                max-width: none;
            }

            .toast {
                transform: translateY(-100px);
            }

            .toast.show {
                transform: translateY(0);
            }

            .toast.hide {
                transform: translateY(-100px);
            }
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Car Rental Pay-In System</h1>
            <p class="text-gray-600">Customers pay for car rentals directly to your merchant account</p>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">${{ number_format($totalPayments, 2) }}</div>
                    <div class="text-green-800 text-sm">Total Revenue</div>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $totalCount }}</div>
                    <div class="text-blue-800 text-sm">Successful Payments</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ $payments->count() }}</div>
                    <div class="text-purple-800 text-sm">Recent Payments</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Create Customer -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Create Customer</h2>
                <form id="create-customer-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer Email</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="customer@example.com">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer Name</label>
                        <input type="text" name="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="John Doe">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone (Optional)</label>
                        <input type="text" name="phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="+1234567890">
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Create Customer
                    </button>
                </form>
            </div>

            <!-- Add Payment Method -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Add Payment Method</h2>
                <form id="payment-method-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer ID</label>
                        <input type="text" name="customer_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="cus_xxxxxxxxxxxxx">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                        <input type="text" name="number" required placeholder="4242424242424242"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Exp Month</label>
                            <input type="number" name="exp_month" min="1" max="12" required
                                placeholder="12"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Exp Year</label>
                            <input type="number" name="exp_year" min="2024" required placeholder="2025"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">CVC</label>
                            <input type="text" name="cvc" required placeholder="123"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                        <input type="text" name="name" required placeholder="John Doe"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <button type="submit"
                        class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        Add Payment Method
                    </button>
                </form>
            </div>

            <!-- Process Payment -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Process Payment</h2>
                <form id="payment-form">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer ID</label>
                        <input type="text" name="customer_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="cus_xxxxxxxxxxxxx">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method ID</label>
                        <input type="text" name="payment_method_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="pm_xxxxxxxxxxxxx">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (USD)</label>
                        <input type="number" name="amount" step="0.01" min="0.50" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="150.00">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description" placeholder="Car rental payment"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rental ID (Optional)</label>
                        <input type="text" name="rental_id" placeholder="RNT-2025-001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <button type="submit"
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Charge Customer
                    </button>
                </form>
            </div>

        </div>

        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold mb-4">Recent Payments</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payment ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">
                                    {{ substr($payment->stripe_payment_intent_id, 0, 20) }}...
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ substr($payment->customer_id, 0, 20) }}...
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                    ${{ number_format($payment->amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($payment->status === 'succeeded')
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Succeeded
                                        </span>
                                    @elseif($payment->status === 'pending')
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    @else
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $payment->description ?: 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $payment->created_at->format('M d, Y H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let toasts = [];

        function getToastContainer() {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            return container;
        }

        function createToast(type, title, message, duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ',
                warning: '⚠'
            };

            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || '•'}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="removeToast(this.parentElement)">×</button>
                ${duration > 0 ? '<div class="toast-progress"></div>' : ''}
            `;

            if (duration > 0) {
                const progressBar = toast.querySelector('.toast-progress');
                setTimeout(() => {
                    progressBar.style.transform = 'scaleX(0)';
                    progressBar.style.transition = `transform ${duration}ms linear`;
                }, 100);
            }

            return toast;
        }

        function showToast(type, title, message, duration = 5000) {
            const container = getToastContainer();
            const toast = createToast(type, title, message, duration);

            container.appendChild(toast);
            toasts.push(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            if (duration > 0) {
                setTimeout(() => {
                    removeToast(toast);
                }, duration);
            }

            return toast;
        }

        function removeToast(toast) {
            toast.classList.remove('show');
            toast.classList.add('hide');

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.parentElement.removeChild(toast);
                }
                toasts = toasts.filter(t => t !== toast);
            }, 400);
        }

        function showSuccess(title, message, duration = 5000) {
            return showToast('success', title, message, duration);
        }

        function showError(title, message, duration = 5000) {
            return showToast('error', title, message, duration);
        }

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
                name: $('input[name="name"]').val(),
                phone: $('input[name="phone"]').val()
            };

            $.ajax({
                url: '/payin-demo/customer',
                method: 'POST',
                data: formData,
                success: function(response) {
                    showSuccess(
                        'Customer Created!',
                        `Customer ID: ${response.customer_id}`
                    );
                    $('#create-customer-form')[0].reset();

                    $('input[name="customer_id"]').val(response.customer_id);
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Failed to create customer';
                    showError('Error', error);
                }
            });
        });

        // Payment Method Form
        $('#payment-method-form').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                customer_id: $('#payment-method-form input[name="customer_id"]').val(),
                card_type: 'visa',
                number: $('input[name="number"]').val(),
                exp_month: $('input[name="exp_month"]').val(),
                exp_year: $('input[name="exp_year"]').val(),
                cvc: $('input[name="cvc"]').val(),
                name: $('#payment-method-form input[name="name"]').val()
            };

            console.log(formData);
            $.ajax({
                url: '/payin-demo/payment-method',
                method: 'POST',
                data: formData,
                success: function(response) {
                    showSuccess(
                        'Payment Method Added!',
                        `Card ending in ${response.last4} (${response.brand})`
                    );
                    $('#payment-method-form')[0].reset();

                    $('input[name="payment_method_id"]').val(response.payment_method_id);
                },
                error: function(xhr) {
                    console.log(xhr);
                    const error = xhr.responseJSON?.error || 'Failed to add payment method';
                    showError('Error', error);
                }
            });
        });

        // Payment Form
        $('#payment-form').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                customer_id: $('#payment-form input[name="customer_id"]').val(),
                payment_method_id: $('input[name="payment_method_id"]').val(),
                amount: parseFloat($('input[name="amount"]').val()),
                description: $('#payment-form input[name="description"]').val(),
                rental_id: $('input[name="rental_id"]').val()
            };

            $.ajax({
                url: '/payin-demo/process-payment',
                method: 'POST',
                data: formData,
                success: function(response) {
                    showSuccess(
                        'Payment Successful!',
                        `Charged $${response.amount} (Platform fee: $${response.application_fee})`
                    );
                    $('#payment-form')[0].reset();

                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Payment failed';
                    showError('Payment Failed', error);
                }
            });
        });
    </script>
</body>

</html>
