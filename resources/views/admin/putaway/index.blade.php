@extends('adminlte::page')

@section('title', 'Putaway / Penyimpanan')
@section('plugins.Datatables', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Proses Putaway Barang</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-info shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Barang Lolos QC (Siap Simpan)</h3>
    </div>
    <div class="card-body">
        
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>{{ session('success') }}</x-adminlte-alert>
        @endif
        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Error" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <table id="putaway-table" class="table table-bordered table-hover table-striped">
            <thead class="bg-gradient-light">
                <tr>
                    <th width="15%">No. Penerimaan</th>
                    <th width="15%">Referensi PO</th>
                    <th>Sumber Asal</th>
                    <th>Lokasi Tujuan</th>
                    <th width="10%">Lolos QC</th>
                    <th style="width: 140px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td class="align-middle font-weight-bold text-primary">{{ $receiving->nomor_penerimaan }}</td>
                    <td class="align-middle">{{ $receiving->purchaseOrder->nomor_po ?? '-' }}</td>
                    
                    <td class="align-middle">
                        @if($receiving->purchaseOrder && $receiving->purchaseOrder->po_type == 'supplier_po')
                            <span class="badge badge-warning">SUPPLIER</span> 
                            {{ $receiving->purchaseOrder->supplier->nama_supplier ?? '-' }}
                        @else
                            <span class="badge badge-info">INTERNAL</span> 
                            {{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi ?? '-' }}
                        @endif
                    </td>
                    
                    <td class="align-middle">{{ $receiving->lokasi->nama_lokasi ?? '-' }}</td>
                    <td class="align-middle text-center">
                        <span class="badge badge-success">{{ $receiving->qc_at ? $receiving->qc_at->format('d/m H:i') : '-' }}</span>
                    </td>
                    <td class="align-middle text-center">
                        <a href="{{ route('admin.putaway.show', $receiving->id) }}" class="btn btn-info btn-sm shadow-sm">
                            <i class="fas fa-dolly-flatbed mr-1"></i> Atur Rak
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
        $('#putaway-table').DataTable({ 
            "responsive": true,
            "autoWidth": false,
            "ordering": false,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            }
        });
    });
</script>
@stop