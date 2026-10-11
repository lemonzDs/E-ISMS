@extends('layouts.app')
@section('title', $audit->exists ? 'Kemaskini pelan audit' : 'Pelan audit baharu')
@section('content')
<div class="work-heading"><div><h1>{{ $audit->exists ? 'Kemaskini pelan audit' : 'Pelan audit baharu' }}</h1><p class="muted">Tetapkan skop, kriteria dan pasukan sebelum pemeriksaan bermula.</p></div><a href="{{ route('audits.index') }}">Kembali ke daftar</a></div>
<form class="work-panel" method="post" action="{{ $audit->exists ? route('audits.update',$audit) : route('audits.store') }}">@csrf @if($audit->exists)@method('PUT')<input type="hidden" name="lock_version" value="{{ $audit->lock_version }}">@endif
<label class="work-field">Tajuk audit<input name="title" required maxlength="255" value="{{ old('title',$audit->title) }}"></label>
<label class="work-field">Bahagian yang diaudit<select aria-label="Bahagian yang diaudit" name="department_id" required><option value="">Pilih bahagian</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)old('department_id',$audit->department_id)===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></label>
<div class="work-form-grid"><label class="work-field">Tarikh mula<input name="start_date" type="date" required value="{{ old('start_date',$audit->start_date?->toDateString()) }}"></label><label class="work-field">Tarikh tamat<input name="end_date" type="date" required value="{{ old('end_date',$audit->end_date?->toDateString()) }}"></label></div>
<label class="work-field">Skop audit<textarea name="scope" required rows="3" maxlength="5000">{{ old('scope',$audit->scope) }}</textarea></label>
<label class="work-field">Kriteria dan rujukan<textarea aria-label="Kriteria dan rujukan" name="criteria" required rows="3" maxlength="5000">{{ old('criteria',$audit->criteria) }}</textarea><small class="muted">Nyatakan polisi, prosedur atau keperluan yang akan diperiksa.</small></label>
@include('audits.team-fields')
<button class="work-button">Simpan pelan audit</button></form>
@endsection
