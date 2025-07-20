@extends('layouts.app')

@section('title', 'Plaid Transactions')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/plaid-transactions.css') }}">
@endsection

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


@section('content')
<div class="transactions-container">
    <div class="background-effects">
        <div class="floating-orb orb-1"></div>
        <div class="floating-orb orb-2"></div>
        <div class="floating-orb orb-3"></div>
    </div>

    <div class="main-content">
        <div class="header-section">
            <h1 class="main-title">Recent Transactions</h1>
            <p class="subtitle">Your financial activity from the last 30 days</p>
            <div class="title-underline"></div>
        </div>

        @if(count($transactions) > 0)
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <p class="stat-label">Total Transactions</p>
                        <p class="stat-value">{{ count($transactions) }}</p>
                    </div>
                    <div class="stat-icon icon-blue">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <p class="stat-label">Total Spent</p>
                        <p class="stat-value">${{ number_format(collect($transactions)->sum('amount'), 2) }}</p>
                    </div>
                    <div class="stat-icon icon-pink">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <p class="stat-label">Avg Transaction</p>
                        <p class="stat-value">${{ number_format(collect($transactions)->avg('amount'), 2) }}</p>
                    </div>
                    <div class="stat-icon icon-green">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="transactions-table-container">
            <div class="table-header">
                <h2 class="table-title">
                    <svg class="title-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Transaction History
                </h2>
            </div>

            <div class="table-wrapper">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Merchant</th>
                            <th>Amount</th>
                            <th>Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $index => $transaction)
                        <tr class="table-row" style="animation-delay: {{ $index * 50 }}ms;">
                            <td>
                                <div class="date-cell">
                                    <div class="date-icon">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="date-info">
                                        <p class="date-main">{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</p>
                                        <p class="date-relative">{{ \Carbon\Carbon::parse($transaction['date'])->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="merchant-cell">
                                    <div class="merchant-avatar">
                                        {{ strtoupper(substr($transaction['name'], 0, 2)) }}
                                    </div>
                                    <div class="merchant-info">
                                        <p class="merchant-name">{{ $transaction['name'] }}</p>
                                        <p class="merchant-label">Merchant</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="amount-cell">
                                    <span class="amount">${{ number_format($transaction['amount'], 2) }}</span>
                                    @if($transaction['amount'] > collect($transactions)->avg('amount'))
                                    <span class="amount-tag tag-high">High</span>
                                    @elseif($transaction['amount'] < collect($transactions)->avg('amount') / 2)
                                        <span class="amount-tag tag-low">Low</span>
                                        @endif
                                </div>
                            </td>
                            <td>
                                <div class="category-cell">
                                    @if(!empty($transaction['category']))
                                    @foreach($transaction['category'] as $cat)
                                    <span class="category-tag">{{ $cat }}</span>
                                    @endforeach
                                    @else
                                    <span class="category-tag">Uncategorized</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="empty-state">
            <div class="empty-icon">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
            </div>
            <h3 class="empty-title">No Transactions Found</h3>
            <p class="empty-description">No transactions found for the last 30 days.</p>
            <div class="empty-actions">
                <button class="btn-primary">Add Transaction</button>
                <button class="btn-secondary">Import Data</button>
            </div>
        </div>
        @endif
    </div>
</div>


@endsection