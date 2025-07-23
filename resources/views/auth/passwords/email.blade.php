@extends('layouts.app')

@section('title', 'Reset Password')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/reset-password.css') }}">
<style>
    #email::placeholder {
        color: white !important;
        opacity: 0.8;
    }
</style>
@endsection

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center position-relative overflow-hidden" style="font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #581c87 50%, #0f172a 100%);">
    <div class="position-absolute w-100 h-100">
        <div class="position-absolute rounded-circle" style="width: 400px; height: 400px; top: -200px; left: -200px; background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, rgba(139, 92, 246, 0.1) 50%, transparent 100%); animation: pulse 4s ease-in-out infinite;"></div>
        <div class="position-absolute rounded-circle" style="width: 300px; height: 300px; top: 20%; right: -150px; background: radial-gradient(circle, rgba(236, 72, 153, 0.2) 0%, rgba(236, 72, 153, 0.05) 50%, transparent 100%); animation: float 8s ease-in-out infinite reverse;"></div>
        <div class="position-absolute rounded-circle" style="width: 250px; height: 250px; bottom: -125px; left: 10%; background: radial-gradient(circle, rgba(14, 165, 233, 0.25) 0%, rgba(14, 165, 233, 0.08) 50%, transparent 100%); animation: float 6s ease-in-out infinite;"></div>

        <div class="position-absolute w-100 h-100 opacity-5" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 20px 20px;"></div>
    </div>

    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-2xl position-relative overflow-hidden" style="background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(40px); border-radius: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded-4" style="background: linear-gradient(45deg, rgba(139, 92, 246, 0.4), rgba(236, 72, 153, 0.4), rgba(14, 165, 233, 0.4), rgba(139, 92, 246, 0.4)); background-size: 300% 300%; animation: gradientShift 6s ease infinite; z-index: -1; margin: -1px; border-radius: 24px;"></div>

                    <div class="card-header border-0 text-center py-5" style="background: transparent;">
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-lg position-relative" style="width: 80px; height: 80px; background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 50%, #0ea5e9 100%); animation: iconGlow 3s ease-in-out infinite alternate;">
                                <div class="position-absolute w-100 h-100 rounded-circle" style="background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 50%, #0ea5e9 100%); filter: blur(10px); opacity: 0.6; animation: iconPulse 2s ease-in-out infinite;"></div>
                                <svg width="36" height="36" fill="white" viewBox="0 0 24 24" class="position-relative">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="mb-2 fw-bold text-white" style="font-size: 1.75rem; letter-spacing: -0.025em; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">{{ __('Reset Password') }}</h3>
                        <p class="mb-0" style="color: rgba(226, 232, 240, 0.8); font-size: 1rem; font-weight: 400;">Enter your email to receive reset instructions</p>
                    </div>

                    <div class="card-body px-5 pb-5">
                        @if (session('status'))
                        <div class="alert border-0 shadow-lg mb-4 position-relative overflow-hidden" role="alert" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.9) 0%, rgba(16, 185, 129, 0.9) 100%); border-radius: 16px; border: 1px solid rgba(34, 197, 94, 0.3);">
                            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%); animation: shimmer 2s infinite;"></div>
                            <div class="d-flex align-items-center position-relative">
                                <div class="me-3">
                                    <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="text-white fw-medium" style="font-size: 0.95rem;">{{ session('status') }}</span>
                            </div>
                        </div>
                        @endif

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="mb-5">
                                <label for="email" class="form-label fw-semibold mb-3 text-white d-flex align-items-center" style="font-size: 1rem; letter-spacing: 0.025em;">
                                    <svg width="18" height="18" fill="currentColor" class="me-2" viewBox="0 0 24 24">
                                        <path d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ __('Email Address') }}
                                </label>
                                <div class="position-relative">
                                    <input id="email"
                                        type="email"
                                        class="form-control border-0 shadow-lg @error('email') is-invalid border-danger @enderror"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autocomplete="email"
                                        autofocus
                                        placeholder="Enter your email address"
                                        style="padding: 1rem 1.25rem; border-radius: 16px; background: rgba(30, 41, 59, 0.8); color: white; font-size: 1.1rem; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(139, 92, 246, 0.2); backdrop-filter: blur(10px);"
                                        onmouseover="this.style.setProperty('--placeholder-color', 'white')"
                                        onfocus="this.style.setProperty('--placeholder-color', 'white')"
                                        style="padding: 1rem 1.25rem; border-radius: 16px; background: rgba(30, 41, 59, 0.8); color: white; font-size: 1.1rem; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(139, 92, 246, 0.2); backdrop-filter: blur(10px); --placeholder-color: white;"
                                        placeholder-style="color: white;">

                                    <div class="position-absolute top-0 start-0 w-100 h-100 rounded-4 opacity-0 transition-opacity" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(236, 72, 153, 0.1)); pointer-events: none; transition: opacity 0.3s ease;"></div>
                                </div>
                                @error('email')
                                <div class="invalid-feedback d-flex align-items-center mt-3 p-3 rounded-3" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.9rem;">
                                    <svg width="18" height="18" fill="currentColor" class="me-2 text-danger" viewBox="0 0 24 24">
                                        <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <strong class="text-danger">{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>

                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-lg border-0 fw-semibold position-relative overflow-hidden shadow-xl" style="padding: 1.25rem 2rem; background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 50%, #0ea5e9 100%); border-radius: 16px; color: white; font-size: 1.1rem; letter-spacing: 0.025em; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); text-transform: none;">
                                    <span class="position-relative z-index-1 d-flex align-items-center justify-content-center">
                                        <svg width="20" height="20" fill="currentColor" class="me-2" viewBox="0 0 24 24">
                                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                                        </svg>
                                        {{ __('Send Password Reset Link') }}
                                    </span>
                                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="background: linear-gradient(135deg, #a855f7 0%, #f472b6 50%, #38bdf8 100%); transition: opacity 0.4s ease;"></div>
                                    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.2) 50%, transparent 100%); transform: translateX(-100%); animation: buttonShimmer 3s infinite;"></div>
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <a href="{{ route('login') }}" class="text-decoration-none d-inline-flex align-items-center px-4 py-2 rounded-pill transition-all" style="color: rgba(226, 232, 240, 0.8); font-weight: 500; font-size: 0.95rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2);">
                                <svg width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 24 24">
                                    <path d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection