@extends('adminlte::page')

@section('title', 'Daftar Penjualan')
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugin', true)

@section('content_header')
    <h1>Daftar Penjualan</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Transaksi Penjualan</h3>
        <div class="card-tools ml-auto">
            {{-- Tombol hanya muncul jika user punya izin create-sale (PC/KSR Dealer) --}}
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
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th>No. Faktur</th>
                        <th>Dealer</th>
                        <th>Tanggal</th>
                        <th>Konsumen</th>
                        <th>Karyawan</th>
                        <th class="text-right">Total</th>
                        <th style="width: 100px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penjualans as $penjualan)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $penjualan->nomor_faktur }}</strong></td>
                            <td>{{ $penjualan->lokasi->nama_lokasi ?? 'Tidak Diketahui' }}</td>
                            <td>{{ \Carbon\Carbon::parse($penjualan->tanggal_jual)->format('d/m/Y') }}</td>
                            <td>{{ $penjualan->konsumen->nama_konsumen ?? '-' }}</td>
                            <td>{{ $penjualan->sales->nama ?? 'Tanpa Sales' }}</td>
                            <td class="text-right">Rp {{ number_format($penjualan->total_harga ?? 0, 0, ',', '.') }}</td>
                            <td class="text-center">
                                <a href="{{ route('admin.penjualans.show', $penjualan->id) }}"
                                   class="btn btn-info btn-xs" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">Belum ada transaksi penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(function () {
    // Inisialisasi DataTables
    $('#penjualans-table').DataTable({
        responsive: true,
        autoWidth: false,
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
            search: "",
            searchPlaceholder: "Cari data penjualan..."
        },
        dom:
            "<'row'<'col-sm-12 col-md-6'lB><'col-sm-12 col-md-6'f>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            { extend: 'copy', text: '<i class="fas fa-copy"></i> Salin', className: 'btn btn-sm btn-default' },
            { extend: 'csv', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn btn-sm btn-default' },
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-sm btn-default' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-sm btn-default' },
            { extend: 'print', text: '<i class="fas fa-print"></i> Cetak', className: 'btn btn-sm btn-default' },
            { extend: 'colvis', text: '<i class="fas fa-eye"></i> Kolom', className: 'btn btn-sm btn-default' }
        ],
        columnDefs: [
            { orderable: false, targets: [0, 7] },
            { searchable: false, targets: [0, 6, 7] }
        ],
        order: [[3, "desc"]] // urutkan berdasarkan tanggal terbaru
    });
});
</script>
@stop

@section('css')
<style>
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
</style>
@stop