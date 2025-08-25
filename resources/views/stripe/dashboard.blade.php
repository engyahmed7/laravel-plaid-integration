@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Banking Dashboard</h4>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Balance</h5>
                                    <h3>${{ number_format($totalBalance, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Connected Accounts</h5>
                                    <h3>{{ $accountsCount }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Bank Connections</h5>
                                    <h3>{{ $bankConnections->count() }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($bankConnections->isEmpty())
                        <div class="text-center py-5">
                            <h5>No bank accounts connected</h5>
                            <p class="text-muted">Connect your bank account to get started</p>
                            <a href="{{ route('plaid.connect') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Connect Bank Account
                            </a>
                        </div>
                    @else
                        <!-- Bank Connections -->
                        @foreach($bankConnections as $connection)
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-university"></i>
                                        {{ $connection->institution_name }}
                                    </h5>
                                    <span class="badge badge-{{ $connection->status === 'active' ? 'success' : 'warning' }}">
                                        {{ ucfirst($connection->status) }}
                                    </span>
                                </div>

                                <div class="card-body">
                                    @if($connection->hasError())
                                        <div class="alert alert-warning">
                                            <strong>Error:</strong> {{ $connection->error_message }}
                                        </div>
                                    @endif

                                    <!-- Accounts -->
                                    <div class="row">
                                        @foreach($connection->accounts as $account)
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="card-title">{{ $account->display_name }}</h6>
                                                                <p class="card-text">
                                                                    <small class="text-muted">
                                                                        {{ ucfirst($account->account_type) }}
                                                                        @if($account->account_subtype)
                                                                            - {{ ucfirst($account->account_subtype) }}
                                                                        @endif
                                                                    </small>
                                                                </p>
                                                                <h5 class="text-primary">{{ $account->formatted_balance }}</h5>
                                                            </div>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                    Actions
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    @if(!$account->stripe_payment_method_id)
                                                                        <li>
                                                                            <a class="dropdown-item" href="#" onclick="createPaymentMethod({{ $account->id }})">
                                                                                Create Payment Method
                                                                            </a>
                                                                        </li>
                                                                    @endif
                                                                    @if($account->verification_status === 'pending')
                                                                        <li>
                                                                            <a class="dropdown-item" href="#" onclick="verifyAccount({{ $account->id }})">
                                                                                Verify Account
                                                                            </a>
                                                                        </li>
                                                                    @endif
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <!-- Status Indicators -->
                                                        <div class="mt-2">
                                                            @if($account->stripe_payment_method_id)
                                                                <span class="badge badge-success">Stripe Connected</span>
                                                            @endif
                                                            @if($account->isVerified())
                                                            <span class="badge badge-success">Verified</span>
                                                            @elseif($account->verification_status === 'pending')
                                                                <span class="badge badge-warning">Pending Verification</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Connection Actions -->
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-outline-primary" onclick="syncAccounts({{ $connection->id }})">
                                            <i class="fas fa-sync"></i> Sync Accounts
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="syncTransactions({{ $connection->id }})">
                                            <i class="fas fa-download"></i> Sync Transactions
                                        </button>
                                        <small class="text-muted ml-3">
                                            Last synced: {{ $connection->last_sync_at ? $connection->last_sync_at->diffForHumans() : 'Never' }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Add Another Account -->
                        <div class="text-center mt-4">
                            <a href="{{ route('plaid.connect') }}" class="btn btn-outline-primary">
                                <i class="fas fa-plus"></i> Connect Another Account
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Bank Account</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Enter the two micro-deposit amounts sent to your bank account:</p>
                <form id="verificationForm">
                    <div class="form-row">
                        <div class="col">
                            <input type="number" class="form-control" id="amount1" placeholder="Amount 1 (cents)" min="1" max="99">
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" id="amount2" placeholder="Amount 2 (cents)" min="1" max="99">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitVerification()">Verify</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAccountId = null;

function createPaymentMethod(accountId) {
    fetch(`/stripe/create-payment-method/${accountId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment method created successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function verifyAccount(accountId) {
    currentAccountId = accountId;

    document.getElementById('verificationModal').style.display = 'block';
    document.getElementById('verificationModal').classList.add('show');
}

function submitVerification() {
    const amount1 = document.getElementById('amount1').value;
    const amount2 = document.getElementById('amount2').value;

    if (!amount1 || !amount2) {
        alert('Please enter both amounts');
        return;
    }

    fetch(`/stripe/verify-bank-account/${currentAccountId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            amounts: [parseInt(amount1), parseInt(amount2)]
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Account verified successfully!');
            hideModal();
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function hideModal() {
    document.getElementById('verificationModal').style.display = 'none';
    document.getElementById('verificationModal').classList.remove('show');
}

// Add event listeners for modal close buttons
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('verificationModal');
    const closeButtons = modal.querySelectorAll('[data-bs-dismiss="modal"]');

    closeButtons.forEach(button => {
        button.addEventListener('click', hideModal);
    });

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideModal();
        }
    });
});
function syncAccounts(connectionId) {
    fetch(`/plaid/sync-accounts/${connectionId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Synced ${data.accounts_synced} accounts`);
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function syncTransactions(connectionId) {
    fetch(`/plaid/sync-transactions/${connectionId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Synced ${data.transactions_synced} transactions`);
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
</script>
@endsection
