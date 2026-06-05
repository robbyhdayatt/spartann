@extends('adminlte::page')

@section('title', 'Under Construction')

@section('content_header')
@stop

@section('content')
<div class="d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="text-center p-5 maintenance-card">
        {{-- Ikon Animasi --}}
        <div class="mb-4 position-relative d-inline-block">
            <i class="fas fa-cog fa-6x text-primary" style="animation: spin 4s linear infinite; position: relative; z-index: 2;"></i>
            <i class="fas fa-wrench fa-3x text-warning position-absolute" style="bottom: -10px; right: -25px; z-index: 3; transform: rotate(-20deg);"></i>
        </div>
        
        {{-- Teks Konten --}}
        <h2 class="mt-4 poppins-bold" style="color: #2c3e50;">Sistem Dalam Perbaikan</h2>
        <p class="text-muted mt-3 poppins-regular" style="font-size: 1.15rem; line-height: 1.6;">
            Mohon maaf, fitur ini sedang dalam tahap <b>pengembangan</b> atau pemeliharaan rutin.<br>
            Kami sedang bekerja untuk memberikan pengalaman yang lebih baik. Silakan kembali lagi nanti.
        </p>
        
        {{-- Tombol Kembali --}}
        <div class="mt-5">
            <button onclick="window.history.back()" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm poppins-medium">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Halaman Sebelumnya
            </button>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    /* Import Font Poppins */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap');

    /* Setup Font Class */
    .poppins-regular { font-family: 'Poppins', sans-serif; font-weight: 400; }
    .poppins-medium { font-family: 'Poppins', sans-serif; font-weight: 500; }
    .poppins-bold { font-family: 'Poppins', sans-serif; font-weight: 700; }

    /* Desain Kotak Konten */
    .maintenance-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        max-width: 650px;
        width: 100%;
        border-top: 5px solid #007bff;
    }

    /* Animasi Roda Gigi Berputar */
    @keyframes spin {
        100% { transform: rotate(360deg); }
    }

    /* Sembunyikan header default bawaan AdminLTE agar lebih bersih */
    .content-header {
        display: none !important;
    }
</style>
@endpush