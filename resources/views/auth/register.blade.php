@extends('layouts.app')

@section('title', 'Daftar | Tilik')

@section('content')
<div class="page-wrap auth-page">
    <div class="auth-card">
        <div class="auth-card-copy">
            <p class="eyebrow">BUAT AKUN BARU</p>
            <h1>Mulai evaluasi perangkat</h1>
            <p>Daftarkan akun untuk menyimpan riwayat pemeriksaan dan memantau keputusan pembelian yang sudah dibuat.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf

            @if ($errors->any())
                <div class="form-errors" role="alert">{{ $errors->first() }}</div>
            @endif

            <label class="workspace-field">
                <span>Nama lengkap</span>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus>
            </label>

            <label class="workspace-field">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label class="workspace-field">
                <span>Password</span>
                <input type="password" name="password" required>
            </label>

            <label class="workspace-field">
                <span>Konfirmasi password</span>
                <input type="password" name="password_confirmation" required>
            </label>

            <button type="submit" class="button button-dark">Daftar</button>

            <p class="auth-link-row">
                Sudah punya akun?
                <a href="{{ route('login') }}">Masuk</a>
            </p>
        </form>
    </div>
</div>
@endsection
