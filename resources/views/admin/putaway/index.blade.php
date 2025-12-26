@extends('adminlte::page')

@section('title', 'Putaway / Penyimpanan')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Daftar Tunggu Penyimpanan (Putaway)</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Barang Lolos QC yang Siap Disimpan</h3>
    </div>
    <div class="card-body">
        <table id="putaway-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No. Penerimaan</th>
                    <th>No. PO</th>
                    <th>Sumber Asal</th>
                    <th>Lokasi Tujuan</th>
                    <th style="width: 150px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td>{{ $receiving->nomor_penerimaan }}</td>
                    <td>{{ $receiving->purchaseOrder->nomor_po ?? '-' }}</td>
                    
                    {{-- PERBAIKAN DISINI: Cek Tipe PO --}}
                    <td>
                        @if($receiving->purchaseOrder->po_type == 'supplier_po')
                            <span class="badge badge-warning">SUPPLIER</span><br>
                            {{ $receiving->purchaseOrder->supplier->nama_supplier ?? 'Supplier Tidak Ditemukan' }}
                        @else
                            <span class="badge badge-info">INTERNAL</span><br>
                            {{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi ?? 'Gudang Pusat' }}
                        @endif
                    </td>
                    
                    <td>{{ $receiving->lokasi->nama_lokasi ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.putaway.form', $receiving->id) }}" class="btn btn-primary btn-xs">
                           <i class="fas fa-dolly-flatbed"></i> Proses Penyimpanan
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada barang yang menunggu untuk disimpan di lokasi Anda.</td>
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
        $('#putaway-table').DataTable({ 
            "responsive": true,
            "ordering": false // Matikan sorting default agar lebih cepat
        });
    });
</script>
@stop