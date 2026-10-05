<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="e-ISMS SUK Pahang. Ruang kerja pengurusan dokumen keselamatan maklumat, semakan dan kelulusan mengikut peranan.">
    <title>e-ISMS · Pejabat Setiausaha Kerajaan Pahang</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="corporate-portal">
@php
    $workspaceUrl = auth()->check()
        ? (auth()->user()->role === 'admin' ? route('admin.users') : route('documents.index'))
        : route('login');
    $workspaceLabel = auth()->check() ? 'Buka ruang kerja' : 'Log masuk';
@endphp
<a class="skip-link" href="#main">Langkau ke kandungan</a>
<div class="cp-government"><div class="cp-container"><span>Pejabat Setiausaha Kerajaan Pahang</span><span>Rintis dalaman</span></div></div>
<header class="cp-header cp-container">
    <a href="{{ route('home') }}" class="cp-identity" aria-label="e-ISMS SUK Pahang — halaman utama">
        <img src="{{ asset('images/landing/jata-pahang.png') }}" alt="Jata Negeri Pahang" width="100" height="100">
        <span><strong>e-ISMS</strong><span>Sistem Pengurusan<br>Keselamatan Maklumat</span></span>
    </a>
    <nav aria-label="Navigasi portal" class="cp-nav">
        <a href="#mengenai">Mengenai sistem</a>
        <a href="#aliran-kerja">Aliran kerja</a>
        <a href="{{ $workspaceUrl }}" class="cp-button cp-button-dark">{{ $workspaceLabel }} <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 18 18 6M6 6h12v12"/></svg></a>
    </nav>
</header>
<main id="main" tabindex="-1">
    <section class="cp-hero" aria-labelledby="portal-title">
        <img class="cp-hero-image" src="{{ asset('images/landing/ppsas.jpg') }}" alt="Ilustrasi seni bina kompleks Pusat Pentadbiran Sultan Ahmad Shah, PPSAS" width="1536" height="864" fetchpriority="high">
        <div class="cp-container cp-hero-inner">
            <div class="cp-hero-copy">
                <h1 id="portal-title">Pengurusan keselamatan maklumat.</h1>
                <p>Urus dokumen, selaraskan semakan dan rekodkan keputusan dalam satu ruang kerja SUK Pahang.</p>
                <a class="cp-button cp-button-gold" href="{{ $workspaceUrl }}">{{ auth()->check() ? 'Buka ruang kerja' : 'Log masuk ke e-ISMS' }} <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
                <span class="cp-access-note">Akses untuk pengguna yang diberi kebenaran.</span>
            </div>
        </div>
    </section>
    <section id="mengenai" class="cp-about cp-container" aria-labelledby="about-title">
        <div><h2 id="about-title">Maklumat terpelihara.<br>Urusan lebih teratur.</h2></div>
        <div class="cp-about-copy"><p>e-ISMS menyokong pengurusan keselamatan maklumat di Pejabat Setiausaha Kerajaan Pahang melalui rekod kerja yang tersusun dan boleh dijejaki.</p><p>Fasa rintis ini memfokuskan kawalan dokumen: daripada penyediaan draf dan semakan hingga kelulusan serta penyimpanan sejarah versi.</p><a href="#aliran-kerja" class="cp-text-link">Lihat cara kerja <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 4v15m-6-6 6 6 6-6"/></svg></a></div>
    </section>
    <section id="aliran-kerja" class="cp-workflow" aria-labelledby="workflow-title">
        <div class="cp-container">
            <div class="cp-section-heading"><h2 id="workflow-title">Satu aliran. Rekod yang lengkap.</h2><p>Kawalan dokumen mengikut tanggungjawab.</p></div>
            <ol class="cp-steps">
                <li><span class="cp-step-number" aria-hidden="true">01</span><h3>Sediakan</h3><p>Pegawai menyediakan draf dan memuat naik dokumen untuk semakan.</p></li>
                <li><span class="cp-step-number" aria-hidden="true">02</span><h3>Semak</h3><p>Penyelaras menyemak kandungan dan merekodkan ulasan pembetulan.</p></li>
                <li><span class="cp-step-number" aria-hidden="true">03</span><h3>Luluskan</h3><p>Pelulus merekodkan keputusan selepas semakan diselesaikan.</p></li>
                <li><span class="cp-step-number" aria-hidden="true">04</span><h3>Jejaki</h3><p>Rujuk versi dokumen, ulasan dan sejarah tindakan dalam satu rekod.</p></li>
            </ol>
        </div>
    </section>
    <aside class="cp-access cp-container" aria-label="Maklumat akses"><div><h2>Akses ruang kerja anda</h2><p>Akaun dan peranan pengguna diuruskan oleh pentadbir sistem.</p></div><a href="{{ $workspaceUrl }}" class="cp-button cp-button-dark">{{ $workspaceLabel }} <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a></aside>
</main>
<footer class="cp-footer"><div class="cp-container"><div class="cp-footer-top"><div><strong>e-ISMS <span> / SUK Pahang</span></strong><p>Pejabat Setiausaha Kerajaan Pahang<br>Pusat Pentadbiran Sultan Ahmad Shah, Kuantan.</p></div><p class="cp-pilot">Versi rintis · Kegunaan dalaman<br>Gunakan data contoh untuk semakan sistem.</p></div><div class="cp-footer-bottom"><span>Sistem Pengurusan Keselamatan Maklumat</span></div></div></footer>
</body>
</html>
