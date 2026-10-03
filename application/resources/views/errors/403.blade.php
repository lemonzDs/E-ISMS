@extends('layouts.app')
@section('title','Akses tidak dibenarkan')
@section('content')
<section class="work-panel"><h1>Akses tidak dibenarkan</h1><p>Peranan, bahagian atau status dokumen tidak membenarkan tindakan ini. Hubungi penyelaras jika skop akses perlu disemak.</p><a class="work-button" href="{{ route('home') }}">Kembali ke portal</a></section>
@endsection
