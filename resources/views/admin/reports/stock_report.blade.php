@extends('adminlte::page')

@section('title', 'Laporan Stok Rinci')

@section('plugins.Datatables', true)

@section('content_header')
    <h1>Laporan Stok Rinci</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Rincian Stok Barang di Semua Lokasi</h3>
        <div class="card-tools">
            {{-- TOMBOL EXPORT BARU --}}
            <a href="{{ route('admin.reports.stock-report.export') }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>
    </div>
    <div class="card-body">
        <table id="stock-report-table" class="table table-bordered table-striped">
            {{-- Isi tabel sama seperti sebelumnya --}}
            <thead>
                <tr>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Lokasi</th>
                    <th>Rak</th>
                    <th class="text-right">Stok</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventoryDetails as $item)
                <tr>
                    <td>{{ $item->barang->part_code ?? '-' }}</td>
                    <td>{{ $item->barang->part_name ?? '-' }}</td>
                    <td>{{ $item->lokasi->nama_lokasi ?? '-' }}</td>
                    <td>{{ $item->rak->kode_rak ?? '-' }}</td>
                    <td class="text-right font-weight-bold">{{ $item->quantity }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop
{{-- JS tetap sama --}}
@section('js')
<script>
    $(document).ready(function() {
        $('#stock-report-table').DataTable({
            "responsive": true,
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        });
    });
</script>
@stop
