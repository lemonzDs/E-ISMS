@extends('layouts.app')
@section('title','Urus akaun')
@section('content')
@if($account->registration_pending)<div class="notice" role="status"><strong>Permohonan pendaftaran pegawai.</strong> Sahkan identiti, e-mel rasmi dan bahagian melalui saluran dalaman. Pilih status <strong>Aktif</strong> dan simpan untuk meluluskan akses. Kekalkan <strong>Tidak aktif</strong> jika semakan belum selesai. Maklumkan keputusan kepada pemohon; e-mel automatik belum tersedia.</div>@endif
@if($pendingOwnership)<div class="notice">Pengguna ini memiliki {{ $pendingOwnership }} dokumen belum diluluskan. Pertukaran bahagian atau peranan yang menghalang kerja akan ditolak. Akaun masih boleh dinyahaktifkan; dokumen perlu menunggu pengaktifan semula sehingga proses pemindahan pemilik tersedia.</div>@endif
<div class="work-heading"><div><a href="{{ route('admin.users') }}">← Pengguna</a><h1>Urus akaun</h1><p class="muted">{{ $account->name }}</p></div></div><section class="work-panel work-form"><form method="post" action="{{ route('admin.users.update',$account) }}">@csrf @method('put')@include('admin.user-fields')<div class="work-actions"><button class="work-button">Simpan akaun</button><a class="work-button secondary" href="{{ route('admin.users') }}">Batal</a></div></form></section>
@endsection
