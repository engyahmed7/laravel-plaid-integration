@extends('layouts.app')

@section('title', 'Connect Bank Account')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/plaid-index.css') }}">
@endsection

@section('scripts')
<script src="{{ asset('js/plaid-link.js') }}"></script>
@endsection

@section('content')
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

@if(!$link_token)
<div class="alert alert-danger">
    Plaid link token is missing. Check your Plaid API or logs.
</div>
@endif

<div class="orb orb1"></div>
<div class="orb orb2"></div>
<div class="orb orb3"></div>

<div class="container">
    <div class="main-card">
        <div class="icon-container"></div>

        <h1 class="title">Connect Your Bank</h1>
        <p class="subtitle">
            Securely link your bank account to view transactions, track your balance, and manage your finances—all in one place.
        </p>

        <button id="link-button" class="connect-btn" data-token="{{ $link_token }}">
            <span class=" loading" id="loading"></span>
            <span id="btn-text">🔗 Connect with Plaid</span>
        </button>

        <div class="features">
            <div class="feature">
                <span class="feature-icon">🔒</span>
                <div class="feature-text">Bank-Level Security</div>
            </div>
            <div class="feature">
                <span class="feature-icon">⚡</span>
                <div class="feature-text">Instant Sync</div>
            </div>
            <div class="feature">
                <span class="feature-icon">🛡️</span>
                <div class="feature-text">256-bit Encryption</div>
            </div>
        </div>

        <div class="security-note">
            <span class="shield">🛡️</span>
            <p>Your banking credentials are never shared with us. We use Plaid's secure infrastructure to protect your financial data.</p>
        </div>

        <div class="alert alert-success" id="success-alert" style="display: none;">
            ✅ Bank account connected successfully!
        </div>

        <div class="alert alert-danger" id="error-alert" style="display: none;">
            ❌ Connection failed. Please try again.
        </div>
    </div>
</div>

<script>
    document.addEventListener('mousemove', (e) => {
        const orbs = document.querySelectorAll('.orb');
        const x = e.clientX / window.innerWidth;
        const y = e.clientY / window.innerHeight;

        orbs.forEach((orb, index) => {
            const speed = (index + 1) * 0.5;
            const xOffset = (x - 0.5) * speed * 20;
            const yOffset = (y - 0.5) * speed * 20;

            orb.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
        });
    });

    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;

        const orbs = document.querySelectorAll('.orb');
        orbs.forEach(orb => {
            orb.style.transform = `translate3d(0, ${rate}px, 0)`;
        });
    });
</script>
@endsection