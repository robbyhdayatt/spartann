@extends('adminlte::page')

@section('title', 'Daftar Penerimaan Barang')
@section('plugins.Datatables', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Daftar Penerimaan Barang</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-success shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i> Data Transaksi Masuk</h3>
        <div class="card-tools">
            @can('perform-warehouse-ops')
            <a href="{{ route('admin.receivings.create') }}" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Catat Penerimaan Baru
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="receivings-table" class="table table-bordered table-striped table-hover">
            <thead class="bg-gradient-light">
                <tr>
                    <th style="width: 15%">No. Receiving</th>
                    <th style="width: 15%">No. PO</th>
                    <th style="width: 12%">Tanggal</th>
                    <th>Supplier / Sumber</th>
                    <th style="width: 15%">Diterima Oleh</th>
                    <th style="width: 12%" class="text-center">Status</th>
                    <th style="width: 10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td class="font-weight-bold align-middle text-primary">
                        {{ $receiving->nomor_penerimaan }}
                    </td>
                    <td class="align-middle">
                        <a href="{{ route('admin.purchase-orders.show', $receiving->purchase_order_id) }}" class="text-dark">
                            {{ $receiving->purchaseOrder->nomor_po ?? '-' }}
                        </a>
                    </td>
                    <td class="align-middle">{{ $receiving->tanggal_terima->format('d/m/Y') }}</td>
                    <td class="align-middle">
                         @if($receiving->purchaseOrder->supplier)
                            <i class="fas fa-truck text-secondary mr-1"></i> {{ $receiving->purchaseOrder->supplier->nama_supplier }}
                        @elseif($receiving->purchaseOrder->sumberLokasi)
                            <i class="fas fa-warehouse text-info mr-1"></i> {{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="align-middle text-muted text-sm">
                        {{ $receiving->receivedBy->nama ?? 'System' }}
                    </td>
                    <td class="align-middle text-center">
                        <span class="badge {{ $receiving->status_class }} px-2 py-1">
                            {{ $receiving->status_badge }}
                        </span>
                    </td>
                    <td class="align-middle text-center">
                        <a href="{{ route('admin.receivings.show', $receiving->id) }}" class="btn btn-info btn-xs btn-block shadow-sm">
                            <i class="fas fa-eye mr-1"></i> Detail
                        </a>
                    </td>
                </tr>
                @empty
                
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#receivings-table').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[0, "desc"]], // Sort by No Receiving desc
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            }
        });
    });
</script>
@stop