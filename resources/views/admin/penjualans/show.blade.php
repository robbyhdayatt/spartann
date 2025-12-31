@extends('adminlte::page')

@section('title', 'Detail Penjualan')

@section('content_header')
    <h1>Detail Faktur: {{ $penjualan->nomor_faktur }}</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header no-print">
        <div class="d-flex justify-content-between">
            <a href="{{ route('admin.penjualans.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>

            <div class="btn-group">
                {{-- Tombol Export PDF --}}
                <a href="{{ route('admin.penjualans.pdf', $penjualan) }}" class="btn btn-danger">
                    <i class="fas fa-file-pdf mr-1"></i> Export PDF
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        {{-- Menggunakan file faktur_content.blade.php untuk menampilkan detail --}}
        <div class="faktur-box p-3">
            @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .faktur-box { border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); background: white; }
    @media print {
        .no-print, .main-footer, .content-header, .main-header, .main-sidebar {
            display: none !important;
        }
        .content-wrapper {
            background: white !important;
            margin-left: 0 !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
        .faktur-box {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
</style>
@endpush