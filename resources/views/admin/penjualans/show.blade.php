@extends('adminlte::page')

@section('title', 'Detail Penjualan')

@section('content_header')
    <h1>Detail Faktur: {{ $penjualan->nomor_faktur }}</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header no-print">
        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.penjualans.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>

            {{-- ++ PERBAIKAN DI BARIS INI ++ --}}
            <a href="{{ route('admin.penjualans.pdf', $penjualan) }}" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export PDF</a>
            {{-- ++ AKHIR PERBAIKAN ++ --}}

        </div>
    </div>
    <div class="card-body">
        {{-- Menggunakan file faktur_content.blade.php untuk menampilkan detail --}}
        @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
    </div>
</div>
@stop

@push('css')
<style>
    .faktur-box { border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
    @media print {
        .no-print, .main-footer, .content-header {
            display: none !important;
        }
    }
</style>
@endpush
