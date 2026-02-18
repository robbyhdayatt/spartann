@extends('adminlte::page')

@section('title', 'Laporan Stok per Lokasi')
@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Laporan Stok per Lokasi</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Filter Laporan</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.reports.stock-by-warehouse') }}">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="lokasi_id">Pilih Lokasi</label>
                        @if($lokasis->count() == 1)
                            <input type="text" class="form-control" value="{{ $lokasis->first()->nama_lokasi }}" readonly>
                            <input type="hidden" name="lokasi_id" value="{{ $lokasis->first()->id }}">
                        @else
                            <select name="lokasi_id" id="lokasi_id" class="form-control select2" required>
                                <option value="">-- Pilih Lokasi --</option>
                                @foreach ($lokasis as $lokasi)
                                    <option value="{{ $lokasi->id }}" {{ $selectedLokasiId == $lokasi->id ? 'selected' : '' }}>
                                        [{{ $lokasi->tipe }}] - {{ $lokasi->nama_lokasi }} ({{ $lokasi->kode_lokasi }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
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

@if($inventoryItems->isNotEmpty())
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            Stok di {{ $selectedLokasiName ?? 'Lokasi Anda' }}
        </h3>
        @if($selectedLokasiId)
        <div class="card-tools">
            <a href="{{ route('admin.reports.stock-by-warehouse.export', ['lokasi_id' => $selectedLokasiId]) }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
        @endif
    </div>
    <div class="card-body">
        <table id="stock-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Merk</th>
                    <th>Rak</th>
                    
                    {{-- KOLOM HARGA DINAMIS SESUAI GATE --}}
                    @can('report-show-selling-in')
                        <th class="text-right">Selling In</th>
                    @endcan
                    
                    @can('report-show-selling-out-retail')
                        <th class="text-right">Selling Out</th>
                        <th class="text-right">Retail</th>
                    @endcan
                    
                    <th class="text-right">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inventoryItems as $item)
                    <tr>
                        <td>{{ $item->barang->part_code ?? '-' }}</td>
                        <td>{{ $item->barang->part_name ?? '-' }}</td>
                        <td>{{ $item->barang->merk ?? '-' }}</td>
                        <td>{{ $item->rak->kode_rak ?? '-' }}</td>
                        
                        {{-- DATA HARGA --}}
                        @can('report-show-selling-in')
                            <td class="text-right">Rp {{ number_format($item->barang->selling_in ?? 0, 0, ',', '.') }}</td>
                        @endcan
                        
                        @can('report-show-selling-out-retail')
                            <td class="text-right">Rp {{ number_format($item->barang->selling_out ?? 0, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($item->barang->retail ?? 0, 0, ',', '.') }}</td>
                        @endcan

                        <td class="text-right font-weight-bold">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@stop

@push('css')
<style>
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px) !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        padding-left: 0.75rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap4' });
        $('#stock-table').DataTable({ "responsive": true, "autoWidth": false });
    });
</script>
@stop