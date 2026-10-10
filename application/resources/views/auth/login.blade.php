@extends('layouts.auth')
@section('title', 'Log masuk pegawai')
@section('content')
<h1 id="auth-title">Log masuk pegawai</h1>
<p class="auth-description">Gunakan akaun dalaman anda untuk mengakses ruang kerja e-ISMS.</p>
@include('components.feedback')
<form method="post" action="{{ route('login') }}" class="officer-fields">
    @csrf
    <div class="work-field"><label for="email">E-mel</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255" @error('email') aria-invalid="true" @enderror></div>
    <div class="work-field"><label for="password">Kata laluan</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="cp-button cp-button-gold auth-submit" type="submit">Log masuk</button>
</form>
<p class="auth-help">Belum mempunyai akaun? <a href="{{ route('register') }}">Daftar sebagai pegawai</a>.<br>Untuk menetapkan semula kata laluan, hubungi pentadbir sistem.</p>
@endsection
