@extends('layouts.app')
@section('title', 'Papan pemuka')
@section('content')
<div class="work-heading"><div><h1>Papan pemuka</h1><p class="muted">{{ now('Asia/Kuala_Lumpur')->format('d/m/Y') }} · Ringkasan berdasarkan rekod yang anda dibenarkan akses.</p></div><a class="work-button secondary" href="{{ route('dashboard') }}">Muat semula</a></div>
@if(auth()->user()->role === 'admin')
    <div class="work-summary"><a href="#permohonan"><strong>{{ $pendingUsers->total() }}</strong>Permohonan menunggu kelulusan</a><a href="{{ route('admin.users') }}"><strong>{{ $activeUsers }}</strong>Pengguna aktif</a></div>
    <section id="permohonan" class="work-panel"><h2>Permohonan akaun pegawai</h2><p class="muted">Semak identiti dan bahagian sebelum mengaktifkan akaun. Permohonan paling lama dipaparkan dahulu.</p>
    <div class="work-scroll" tabindex="0" role="region" aria-label="Permohonan akaun"><table class="work-table"><thead><tr><th>Pemohon</th><th>Bahagian</th><th>Tarikh daftar</th><th>Tindakan</th></tr></thead><tbody>
    @forelse($pendingUsers as $account)<tr><td>{{ $account->name }}<span class="sub">{{ $account->email }}</span></td><td>{{ $account->department?->name }}</td><td>{{ $account->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y') }}</td><td><a href="{{ route('admin.users.edit', $account) }}">Semak permohonan</a></td></tr>@empty<tr><td colspan="4" class="empty-state">Tiada permohonan akaun menunggu kelulusan.</td></tr>@endforelse
    </tbody></table></div>{{ $pendingUsers->links() }}</section>
@else
    <div class="work-summary"><a href="#dokumen"><strong>{{ $documents->total() }}</strong>Dokumen untuk tindakan anda</a><a href="#risiko"><strong>{{ $risks->total() }}</strong>Risiko untuk tindakan anda</a><a href="#rawatan"><strong>{{ $overdue }}</strong>Tindakan lewat dalam skop akses</a><a href="#rawatan"><strong>{{ $soon }}</strong>Tindakan dengan sasaran 7 hari lagi*</a></div>
    <p class="muted">*Termasuk hari ini. Tindakan yang telah disahkan dan kitaran penilaian terdahulu tidak dikira.</p>
    <section id="dokumen" class="work-panel"><h2>Dokumen untuk tindakan anda</h2><p class="muted">Draf atau pembetulan milik anda, serta semakan dan kelulusan yang dibenarkan mengikut peranan.</p><div class="work-scroll" tabindex="0" role="region" aria-label="Dokumen untuk tindakan"><table class="work-table"><thead><tr><th>Dokumen</th><th>Bahagian</th><th>Status terkini</th></tr></thead><tbody>
    @forelse($documents as $document)<tr><td><a href="{{ route('documents.show', $document) }}">{{ $document->code }} · {{ $document->title }}</a></td><td>{{ $document->department?->name }}</td><td>{{ $document->latestVersion->statusLabel() }}</td></tr>@empty<tr><td colspan="3" class="empty-state">Tiada dokumen memerlukan tindakan anda.</td></tr>@endforelse
    </tbody></table></div>{{ $documents->links() }}</section>
    <section id="risiko" class="work-panel"><h2>Risiko untuk tindakan anda</h2><div class="work-scroll" tabindex="0" role="region" aria-label="Risiko untuk tindakan"><table class="work-table"><thead><tr><th>Risiko</th><th>Bahagian</th><th>Status</th></tr></thead><tbody>
    @forelse($risks as $risk)<tr><td><a href="{{ route('risks.show', $risk) }}">{{ $risk->code() }} · {{ $risk->title }}</a></td><td>{{ $risk->department?->name }}</td><td>{{ $risk->statusLabel() }}</td></tr>@empty<tr><td colspan="3" class="empty-state">Tiada risiko memerlukan tindakan anda.</td></tr>@endforelse
    </tbody></table></div>{{ $risks->links() }}</section>
    <section id="rawatan" class="work-panel"><h2>Pantauan tindakan rawatan</h2><p class="muted">Tindakan lewat, sasaran dalam 7 hari dan bukti menunggu pengesahan, mengikut skop akses anda. Buka rekod untuk melihat tindakan yang dibenarkan.</p><div class="work-scroll" tabindex="0" role="region" aria-label="Pantauan rawatan"><table class="work-table"><thead><tr><th>Tindakan / Risiko</th><th>Pelaksana</th><th>Tarikh sasaran</th><th>Status</th></tr></thead><tbody>
    @forelse($actions as $action)<tr><td><a href="{{ route('risks.treatment', $action->risk) }}">{{ $action->title }}</a><span class="sub">{{ $action->risk->code() }} · {{ $action->risk->title }} · {{ $action->risk->department?->name }}</span></td><td>{{ $action->assignee?->name }}</td><td>{{ $action->due_date->format('d/m/Y') }}@if($action->overdue())<span class="sub">Lewat tarikh sasaran</span>@endif</td><td>{{ $action->statusLabel() }}</td></tr>@empty<tr><td colspan="4" class="empty-state">Tiada tindakan yang hampir tarikh sasaran, lewat atau menunggu pengesahan.</td></tr>@endforelse
    </tbody></table></div>{{ $actions->links() }}</section>
@endif
@endsection
