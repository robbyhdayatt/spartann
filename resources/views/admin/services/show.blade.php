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
            {{-- PERUBAHAN 1: Tombol Kembali --}}
            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            @can('manage-service')
                <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit / Tambah Part
                </a>
            @endcan
            <a href="{{ route('admin.services.pdf', ['id' => $service->id]) }}" class="btn btn-danger" target="_blank">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>
@stop

@section('content')
<div class="invoice p-3 mb-3">
    {{-- Menggunakan file pdf_content yang sama untuk konsistensi --}}
    @include('admin.services.pdf_content', ['service' => $service, 'totalService' => $totalService, 'totalSparepart' => $totalSparepart])
</div>
@stop

@push('css')
{{-- CSS Kustom untuk tampilan web --}}
<style>
    .invoice-box { border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
    /* Menggunakan style dari pdf_content.blade.php, namun bisa ditambahkan override di sini jika perlu */
</style>
@endpush