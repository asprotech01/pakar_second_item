@extends('layouts.app')

@section('title', 'Masuk | Tilik')

@section('content')
<div class="page-wrap auth-page">
    <div class="auth-card">
        <div class="auth-card-copy">
            <p class="eyebrow">AKSES TERBATAS</p>
            <h1>Masuk ke akun</h1>
            <p>Kelola pemeriksaan perangkatmu dan lihat riwayat yang sudah diinspeksi.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="auth-form">
            @csrf

            @if ($errors->any())
                <div class="form-errors" role="alert">{{ $errors->first() }}</div>
            @endif

            <label class="workspace-field">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <label class="workspace-field">
                <span>Password</span>
                <input type="password" name="password" required>
            </label>

            <label class="checkbox-field">
                <input type="checkbox" name="remember" value="1">
                <span>Ingat saya</span>
            </label>

            <button type="submit" class="button button-dark">Masuk</button>

            <p class="auth-link-row">
                Belum punya akun?
                <a href="{{ route('register') }}">Daftar</a>
            </p>
        </form>
    </div>
</div>
@endsection
