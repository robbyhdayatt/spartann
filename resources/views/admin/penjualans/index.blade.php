@extends('adminlte::page')

@section('title', 'Daftar Penjualan')

{{-- Aktifkan plugin DataTables dan Buttons --}}
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugin', true)

@section('content_header')
    <h1>Daftar Penjualan</h1>
@stop

@section('content')
<div class="card card-outline card-info">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Transaksi Penjualan</h3>
        <div class="card-tools ml-auto">
            @can('create-sale')
            <a href="{{ route('admin.penjualans.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Penjualan Baru
            </a>
            @endcan
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table id="penjualans-table" class="table table-bordered table-striped table-hover" style="width:100%">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th>No. Faktur</th>
                        <th>Tanggal</th>
                        <th>Konsumen</th>
                        <th>Sales</th>
                        <th class="text-right">Total</th>
                        <th style="width: 10%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penjualans as $index => $penjualan)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $penjualan->nomor_faktur }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($penjualan->tanggal_jual)->format('d-m-Y') }}</td>
                        <td>{{ $penjualan->konsumen->nama_konsumen ?? '-' }}</td>
                        <td>{{ $penjualan->sales->nama ?? 'Tanpa Sales' }}</td>
                        <td class="text-right">Rp {{ number_format($penjualan->total_harga, 0, ',', '.') }}</td>
                        <td class="text-center">
                            <a href="{{ route('admin.penjualans.show', $penjualan->id) }}"
                               class="btn btn-xs btn-info" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada transaksi penjualan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
$(function () {
    var table = $('#penjualans-table').DataTable({
        "responsive": true,
        "autoWidth": false,
        "paging": true,
        "lengthChange": true,
        "info": true,
        "searching": true,
        "ordering": true,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
            "search": "",
            "searchPlaceholder": "Cari di tabel ini..."
        },
        "dom": "<'row'<'col-sm-12 col-md-6'lB><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "buttons": [
            { extend: 'copy', text: '<i class="fas fa-copy"></i> Salin', className: 'btn btn-sm btn-default' },
            { extend: 'csv', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn btn-sm btn-default' },
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-sm btn-default' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-sm btn-default' },
            { extend: 'print', text: '<i class="fas fa-print"></i> Cetak', className: 'btn btn-sm btn-default' },
            { extend: 'colvis', text: '<i class="fas fa-eye"></i> Kolom', className: 'btn btn-sm btn-default' }
        ],
        "columnDefs": [
            { "orderable": false, "targets": [0, 6] },
            { "searchable": false, "targets": [0, 5, 6] }
        ],
        "order": [[2, "desc"]] // urutkan default berdasarkan tanggal terbaru
    });
});
</script>
@endpush

@push('css')
<style>
    .dataTables_wrapper .row:first-child {
        margin-bottom: 0.5rem;
        padding-top: 0.5rem;
        background-color: #f4f6f9;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.5rem;
    }
    .dataTables_wrapper .dt-buttons {
        text-align: left;
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter {
        text-align: right;
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter input {
        width: 250px;
        display: inline-block;
        margin-left: 0.5rem;
    }
    .dataTables_wrapper .row:last-child {
        padding-top: 1rem;
        border-top: 1px solid #dee2e6;
    }
    .dataTables_wrapper .dataTables_info {
        padding-top: 0.375rem;
    }
    .dataTables_wrapper .dataTables_paginate {
        text-align: right;
    }
</style>
@endpush
