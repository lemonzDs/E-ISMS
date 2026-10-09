@extends('layouts.app')
@section('title',$risk->exists ? 'Kemaskini risiko' : 'Daftar risiko')
@section('content')
<div class="work-heading"><div><a href="{{ $risk->exists ? route('risks.show',$risk) : route('risks.index') }}">Kembali</a><h1>{{ $risk->exists ? 'Kemaskini penilaian' : 'Daftar risiko' }}</h1><p class="muted">{{ auth()->user()->department->name }} · Pemilik: {{ auth()->user()->name }}</p></div></div>
<div class="notice">Penilaian contoh 5×5: kebarangkalian × impak. 1–4 rendah, 5–12 sederhana, 13–25 tinggi. Kaedah belum disahkan SUK. Nyatakan alasan berdasarkan kawalan sedia ada.</div>
<form class="work-panel work-form" method="post" action="{{ $risk->exists ? route('risks.update',$risk) : route('risks.store') }}">@csrf
@if($risk->exists)@method('put')<input type="hidden" name="lock_version" value="{{ old('lock_version',$risk->lock_version) }}">@endif
<h2>Butiran risiko</h2>
@foreach(['title'=>'Tajuk risiko','asset_process'=>'Aset atau proses terlibat'] as $key=>$label)<div class="work-field"><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" name="{{ $key }}" value="{{ old($key,$risk->$key) }}" required maxlength="255"></div>@endforeach
@foreach(['threat'=>'Ancaman','vulnerability'=>'Kelemahan','consequence'=>'Kesan kepada organisasi','existing_controls'=>'Kawalan sedia ada'] as $key=>$label)<div class="work-field"><label for="{{ $key }}">{{ $label }}</label><textarea id="{{ $key }}" name="{{ $key }}" required maxlength="5000">{{ old($key,$risk->$key) }}</textarea></div>@endforeach
<h2>Penilaian dengan kawalan sedia ada</h2>
@foreach(['likelihood'=>['Kebarangkalian',['Sangat jarang','Jarang','Mungkin','Kerap','Sangat kerap']],'impact'=>['Impak',['Sangat kecil','Kecil','Sederhana','Besar','Sangat besar']]] as $key=>[$label,$options])<div class="work-field"><label for="{{ $key }}">{{ $label }}</label><select id="{{ $key }}" name="{{ $key }}" required><option value="">Pilih tahap</option>@foreach($options as $option)<option value="{{ $loop->iteration }}" @selected((string) old($key,$risk->$key)===(string)$loop->iteration)>{{ $loop->iteration }} · {{ $option }}</option>@endforeach</select></div>@endforeach
<div class="work-field"><label for="rationale">Alasan penilaian</label><textarea id="rationale" name="rationale" required maxlength="5000">{{ old('rationale',$risk->rationale) }}</textarea><small>Terangkan sebab tahap kebarangkalian dan impak dipilih. Skor dikira apabila rekod disimpan.</small></div>
<div class="work-actions"><button class="work-button">Simpan draf</button><a href="{{ $risk->exists ? route('risks.show',$risk) : route('risks.index') }}">Batal</a></div></form>
@endsection
