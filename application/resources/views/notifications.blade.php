@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<div class="work-heading"><div><h1>Notifikasi</h1><p class="muted">{{ $unreadCount }} belum dibaca. Status terkini dan tindakan yang dibenarkan boleh dilihat dalam rekod berkaitan.</p></div>@if($unreadCount)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="work-button secondary">Tandakan semua dibaca</button></form>@endif</div>
<div class="work-actions notification-filters" aria-label="Tapis notifikasi"><a class="work-button secondary" href="{{ route('notifications.index') }}" @if(request('filter','all') === 'all') aria-current="page" @endif>Semua</a><a class="work-button secondary" href="{{ route('notifications.index', ['filter' => 'unread']) }}" @if(request('filter') === 'unread') aria-current="page" @endif>Belum dibaca</a></div>
<section class="work-panel" aria-label="Senarai notifikasi">
<ul class="notification-list">
@forelse($notifications as $notification)
    <li>
        <div><span class="status-label">{{ $notification->read_at ? 'Dibaca' : 'Belum dibaca' }}</span><p>{{ $notification->data['message'] }}</p><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }} · Waktu Malaysia</time></div>
        <div class="work-actions"><form method="post" action="{{ route('notifications.open', $notification->id) }}">@csrf<button class="work-button">Buka rekod</button></form>@unless($notification->read_at)<form method="post" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="work-button secondary">Tandakan dibaca</button></form>@endunless</div>
    </li>
@empty
    <li class="empty-state">{{ request('filter') === 'unread' ? 'Tiada notifikasi belum dibaca.' : 'Tiada notifikasi lagi. Perubahan urusan selepas ciri ini diaktifkan akan dipaparkan di sini.' }}</li>
@endforelse
</ul>
{{ $notifications->links() }}
</section>
<p class="muted">Notifikasi disimpan dalam aplikasi dan dikemas kini apabila halaman dimuat semula. Jika akses anda berubah, rekod berkaitan mungkin tidak lagi boleh dibuka.</p>
@endsection
