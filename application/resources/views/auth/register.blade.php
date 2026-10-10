@extends('layouts.auth')
@section('title', 'Daftar pegawai')
@section('content')
<h1 id="auth-title">Daftar pegawai</h1>
<p class="auth-description">Mohon akses e-ISMS. Pentadbir akan menyemak maklumat anda sebelum mengaktifkan akaun.</p>
@include('components.feedback')
<form method="post" action="{{ route('register.store') }}" class="officer-fields">
    @csrf
    <div class="work-field"><label for="name">Nama penuh</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="255" @error('name') aria-invalid="true" @enderror></div>
    <div class="work-field"><label for="email">E-mel</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="255" aria-describedby="email-hint" @error('email') aria-invalid="true" @enderror><small id="email-hint">Gunakan e-mel rasmi yang boleh disahkan oleh pentadbir.</small></div>
    <div class="work-field"><label for="department_id">Bahagian</label><select id="department_id" name="department_id" required @error('department_id') aria-invalid="true" @enderror><option value="">Pilih bahagian anda</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="auth-passwords">
        <div class="work-field"><label for="password">Kata laluan</label><input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72" aria-describedby="password-hint" @error('password') aria-invalid="true" @enderror><small id="password-hint">12 hingga 72 aksara.</small></div>
        <div class="work-field"><label for="password_confirmation">Sahkan kata laluan</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></div>
    </div>
    <button class="cp-button cp-button-gold auth-submit" type="submit">Hantar permohonan</button>
</form>
<p class="auth-help">Pendaftaran tidak memberi akses secara automatik. Hubungi pentadbir untuk semakan status permohonan.</p>
@endsection
