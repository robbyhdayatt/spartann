@extends('adminlte::page')

@section('title', 'Laporan Stok per Lokasi') {{-- DIUBAH --}}

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Laporan Stok per Lokasi</h1> {{-- DIUBAH --}}
@stop

@section('content')
@php
    // Logika untuk menentukan siapa yang bisa mem-filter (SA, PIC, MA)
    // Selain peran ini (misal KG, KC), mereka akan otomatis melihat lokasi mereka.
    $canFilter = Auth::user()->hasRole(['SA', 'PIC', 'MA']);
@endphp

{{-- Form Filter hanya ditampilkan jika user BISA mem-filter --}}
@if($canFilter)
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Filter Laporan</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.reports.stock-by-warehouse') }}">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group">
                        {{-- DIUBAH: Label dan ID --}}
                        <label for="lokasi_id">Pilih Lokasi (Gudang / Dealer)</label>
                        {{-- DIUBAH: name, id --}}
                        <select name="lokasi_id" id="lokasi_id" class="form-control select2" required>
                            <option value="">-- Pilih Lokasi --</option>
                            {{-- DIUBAH: Variabel $lokasiList (dari Controller) --}}
                            @foreach ($lokasiList as $lokasi)
                                {{-- DIUBAH: Variabel $selectedLokasi (dari Controller) --}}
                                <option value="{{ $lokasi->id }}" {{ optional($selectedLokasi)->id == $lokasi->id ? 'selected' : '' }}>
                                    {{-- DIUBAH: Tampilkan tipe dan nama lokasi --}}
                                    [{{ $lokasi->tipe }}] - {{ $lokasi->nama_lokasi }} ({{ $lokasi->kode_lokasi }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Tampilkan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Tabel Hasil akan ditampilkan jika ada data ATAU jika login sebagai staf (non-filter) --}}
@if($inventoryItems->isNotEmpty() || !$canFilter)
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            Rincian Stok Part Tersedia
            {{-- DIUBAH: Variabel $selectedLokasi --}}
            @if($selectedLokasi)
                di {{ $selectedLokasi->nama_lokasi }}
            @endif
        </h3>
        @if($selectedLokasi)
        <div class="card-tools">
            {{-- DIUBAH: Parameter route ke lokasi_id --}}
            <a href="{{ route('admin.reports.stock-by-warehouse.export', ['lokasi_id' => $selectedLokasi->id]) }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
        @endif
    </div>
    <div class="card-body">
        @if($inventoryItems->isNotEmpty())
            <table id="stock-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Kode Part</th>
                        <th>Nama Part</th>
                        <th>Brand</th>
                        <th>Kategori</th>
                        <th>Rak</th>
                        <th class="text-right">Qty</th>
                        <th>Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inventoryItems as $item)
                        <tr>
                            <td>{{ $item->part->kode_part }}</td>
                            <td>{{ $item->part->nama_part }}</td>
                            <td>{{ $item->part->brand->nama_brand ?? 'N/A' }}</td>
                            <td>{{ $item->part->category->nama_kategori ?? 'N/A' }}</td>
                            <td>{{ $item->rak->kode_rak ?? 'N/A' }}</td>
                            <td class="text-right">{{ $item->quantity }}</td>
                            <td>{{ $item->part->satuan }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
             {{-- DIUBAH: Teks "gudang" menjadi "lokasi" --}}
            <p class="text-center mt-4">Tidak ada data stok untuk lokasi ini.</p>
        @endif
    </div>
</div>
@endif

@stop

@push('css')
<style>
    /* Menyesuaikan tinggi Select2 agar sama dengan input form lainnya */
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: .75rem !important;
        padding-top: .375rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        $('.select2').select2();
        $('#stock-table').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "buttons": ["excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#stock-table_wrapper .col-md-6:eq(0)');
    });
</script>
@stop
