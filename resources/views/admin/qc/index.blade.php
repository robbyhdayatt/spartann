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
        <table id="qc-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No. Penerimaan</th>
                    <th>No. PO</th>
                    <th>Supplier</th>
                    <th>Lokasi</th> {{-- Diubah --}}
                    <th>Tanggal Terima</th>
                    <th style="width: 150px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td>{{ $receiving->nomor_penerimaan }}</td>
                    <td>{{ $receiving->purchaseOrder->nomor_po }}</td>
                    <td>{{ $receiving->purchaseOrder->supplier->nama_supplier }}</td>
                    <td>{{ $receiving->lokasi->nama_gudang }}</td> {{-- Diubah --}}
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
            "order": [[ 4, "asc" ]] // Urutkan berdasarkan tanggal
        });
    });
</script>
@stop