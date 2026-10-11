@extends('layouts.app')
@section('title','Rekod telah berubah')
@section('content')
<section class="work-panel"><h1>Semak rekod terkini</h1><p>{{ $exception->getMessage() ?: 'Rekod telah berubah selepas halaman ini dibuka.' }}</p><p>Perubahan anda belum disimpan. Buka semula rekod, semak status dan masukkan semula perubahan jika masih diperlukan.</p><a class="work-button" href="{{ route(request()->is('audits*','audit-*') ? 'audits.index' : (request()->is('risks*','risk-*') ? 'risks.index' : 'documents.index')) }}">Kembali ke daftar</a></section>
@endsection
