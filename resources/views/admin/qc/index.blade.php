@extends('adminlte::page')

@section('title', 'Quality Control')
@section('plugins.Datatables', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Quality Control (QC)</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-warning shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clipboard-check mr-1"></i> Antrian Pemeriksaan Barang</h3>
    </div>
    <div class="card-body">

        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>{{ session('success') }}</x-adminlte-alert>
        @endif
        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Error" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <table id="qc-table" class="table table-bordered table-hover table-striped">
            <thead class="bg-gradient-light">
                <tr>
                    <th>No. Penerimaan</th>
                    <th>Referensi PO</th>
                    <th>Sumber (Supplier / Gudang)</th>
                    <th>Tanggal Terima</th>
                    <th class="text-center" style="width: 120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td class="align-middle font-weight-bold text-primary">{{ $receiving->nomor_penerimaan }}</td>
                    <td class="align-middle">
                        <a href="{{ route('admin.purchase-orders.show', $receiving->purchase_order_id) }}" class="text-dark">
                            {{ $receiving->purchaseOrder->nomor_po ?? '-' }}
                        </a>
                    </td>
                    
                    <td class="align-middle">
                        @if($receiving->purchaseOrder)
                            @if($receiving->purchaseOrder->po_type == 'supplier_po')
                                <span class="badge badge-secondary"><i class="fas fa-truck"></i> SUPPLIER</span><br>
                                <small>{{ $receiving->purchaseOrder->supplier->nama_supplier ?? '-' }}</small>
                            @else
                                <span class="badge badge-info"><i class="fas fa-warehouse"></i> INTERNAL</span><br>
                                <small>{{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi ?? '-' }}</small>
                            @endif
                        @else
                            -
                        @endif
                    </td>

                    <td class="align-middle">{{ $receiving->tanggal_terima->format('d/m/Y') }}</td>
                    <td class="align-middle text-center">
                        <a href="{{ route('admin.qc.show', $receiving->id) }}" class="btn btn-warning btn-sm shadow-sm">
                            <i class="fas fa-tasks mr-1"></i> Proses QC
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
        $('#qc-table').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[ 3, "asc" ]], // Urutkan berdasarkan tanggal terlama dulu (FIFO Work)
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            }
        });
    });
</script>
@stop