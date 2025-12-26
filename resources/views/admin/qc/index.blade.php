@extends('adminlte::page')

@section('title', 'Quality Control')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Daftar Tunggu Quality Control (QC)</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Penerimaan yang Membutuhkan Pengecekan</h3>
    </div>
    <div class="card-body">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif

        <table id="qc-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No. Penerimaan</th>
                    <th>No. PO</th>
                    <th>Sumber (Supplier / Internal)</th>
                    <th>Lokasi Penerima</th>
                    <th>Tanggal Terima</th>
                    <th style="width: 150px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td>{{ $receiving->nomor_penerimaan }}</td>
                    <td>{{ $receiving->purchaseOrder->nomor_po ?? '-' }}</td>
                    
                    {{-- PERBAIKAN KOLOM SUPPLIER --}}
                    <td>
                        @if($receiving->purchaseOrder)
                            @if($receiving->purchaseOrder->po_type == 'supplier_po')
                                <span class="badge badge-warning">SUPPLIER</span> 
                                {{ $receiving->purchaseOrder->supplier->nama_supplier ?? 'Unknown' }}
                            @else
                                <span class="badge badge-info">INTERNAL</span> 
                                {{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi ?? 'Pusat' }}
                            @endif
                        @else
                            -
                        @endif
                    </td>

                    <td>{{ $receiving->lokasi->nama_lokasi ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($receiving->tanggal_terima)->format('d-m-Y') }}</td>
                    <td>
                        <a href="{{ route('admin.qc.form', $receiving->id) }}" class="btn btn-primary btn-xs">
                            <i class="fas fa-check"></i> Proses QC
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada barang yang menunggu proses QC di lokasi Anda.</td>
                </tr>
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
            "order": [[ 4, "asc" ]] 
        });

        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){ $(this).remove(); });
        }, 5000);
    });
</script>
@stop