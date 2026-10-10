<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · e-ISMS SUK Pahang</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="corporate-portal officer-portal">
<a class="skip-link" href="#main">Langkau ke kandungan</a>
<div class="cp-government"><div class="cp-container"><span>Pejabat Setiausaha Kerajaan Pahang</span><span>Rintis dalaman</span></div></div>
<header class="cp-header cp-container">
    <a href="{{ route('home') }}" class="cp-identity" aria-label="e-ISMS SUK Pahang — halaman utama">
        <img src="{{ asset('images/landing/jata-pahang.png') }}" alt="Jata Negeri Pahang" width="100" height="100">
        <span><strong>e-ISMS</strong><span>Sistem Pengurusan<br>Keselamatan Maklumat</span></span>
    </a>
    <a class="auth-home" href="{{ route('home') }}"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m10 5-7 7 7 7M3 12h18"/></svg><span>Laman utama</span></a>
</header>
<main id="main" class="officer-main" tabindex="-1">
    <div class="officer-scene">
        <img src="{{ asset('images/landing/ppsas.jpg') }}" alt="Ilustrasi seni bina PPSAS" width="1536" height="864" fetchpriority="high">
        <div class="officer-intro"><p class="officer-institution">e-ISMS / SUK Pahang</p><h2>Maklumat terpelihara.<br>Urusan lebih teratur.</h2><p>Ruang kerja pegawai untuk mengurus dokumen, menilai risiko dan menyelaras tindakan keselamatan maklumat.</p></div>
    </div>
    <section class="officer-access" aria-labelledby="auth-title">
        <div class="officer-form">
            <nav class="auth-tabs" aria-label="Akses pegawai">
                <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Log masuk</a>
                <a href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Daftar pegawai</a>
            </nav>
            @yield('content')
        </div>
    </section>
</main>
<footer class="officer-footer cp-container"><span>Pejabat Setiausaha Kerajaan Pahang</span><span>Versi rintis · Gunakan data contoh sahaja</span></footer>
</body>
</html>
