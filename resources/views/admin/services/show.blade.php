@php
use App\Helpers\NumberHelper;
// Kalkulasi Total Service dan Sparepart
$totalService = $service->details->where('item_category', 'JASA')->sum(function ($item) {
    return $item->quantity * $item->price;
});
$totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(function ($item) {
    return $item->quantity * $item->price;
});
@endphp

@extends('adminlte::page')

@section('title', 'Invoice ' . $service->invoice_no)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Faktur Service: {{ $service->invoice_no }}</h1>
        <div>
            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            
            {{-- Tombol diubah menjadi Print Browser agar hasil editan layar bisa ikut tercetak --}}
            <button onclick="window.print()" class="btn btn-danger">
                <i class="fas fa-print"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>
@stop

@section('content')
<div class="alert alert-info alert-dismissible no-print">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    <h5><i class="icon fas fa-info-circle"></i> Mode Edit Cepat!</h5>
    Beberapa area pada invoice di bawah ini (yang memiliki garis putus-putus) <b>bisa Anda klik dan ketik/edit secara langsung</b> sebelum dicetak. Perubahan angka di sini tidak akan mengubah data asli di database.
</div>

<div class="invoice p-3 mb-3" id="print-area">
    @include('admin.services.pdf_content', ['service' => $service, 'totalService' => $totalService, 'totalSparepart' => $totalSparepart])
</div>
@stop

@push('css')
<style>
    .invoice-box { 
        border: 1px solid #ddd; 
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
        background-color: #fff; 
    }
    
    /* === PERINTAH KHUSUS UNTUK BROWSER PRINT === */
    @media print {
        /* 1. Sembunyikan Menu Sidebar, Navbar, dan Footer AdminLTE */
        .main-header, .main-sidebar, .main-footer, .no-print {
            display: none !important;
        }

        /* 2. HILANGKAN SEMUA MARGIN & PADDING BAWAAN TEMPLATE (Biar Mentok) */
        html, body, .wrapper, .content-wrapper, .content, .invoice, .invoice-box {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            background-color: #fff !important;
        }

        /* 3. Atur Kertas Continuous Form dengan Margin 0 (Mentok Ujung Kertas) */
        @page {
            size: 21.5cm 14cm; 
            margin: 0mm !important; /* Margin 0 agar mentok */
        }

        /* 4. Beri jarak nafas super tipis (2mm) hanya di dalam box agar teks tidak terpotong fisik printer */
        .invoice-box {
            border: none !important;
            box-shadow: none !important;
            padding: 2mm !important; 
        }

        /* 5. Paksa browser mencetak warna dan garis tabel */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        /* 6. Mencegah baris terpotong di tengah */
        tr {
            page-break-inside: avoid;
        }
    }
</style>
@endpush