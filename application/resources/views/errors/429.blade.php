@extends('layouts.app')
@section('title','Terlalu banyak percubaan')
@section('content')
<div class="auth-wrap"><h1>Cuba semula sebentar lagi</h1><p>Terlalu banyak percubaan log masuk. Tunggu satu minit sebelum mencuba semula.</p><a class="work-button" href="{{ route('login') }}">Kembali ke log masuk</a></div>
@endsection
