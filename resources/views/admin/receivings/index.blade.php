@extends('adminlte::page')

@section('title', 'Penerimaan Barang')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Penerimaan Barang</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Penerimaan</h3>
        <div class="card-tools">
            @can('perform-warehouse-ops') {{-- Menggunakan gate baru --}}
            <a href="{{ route('admin.receivings.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Catat Penerimaan Baru</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="receivings-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No. Penerimaan</th>
                    <th>No. PO</th>
                    <th>Tanggal</th>
                    <th>Lokasi</th> {{-- Label diubah --}}
                    <th>Status</th>
                    <th style="width: 100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivings as $receiving)
                <tr>
                    <td>{{ $receiving->nomor_penerimaan }}</td>
                    <td>{{ $receiving->purchaseOrder->nomor_po }}</td>
                    <td>{{ \Carbon\Carbon::parse($receiving->tanggal_terima)->format('d-m-Y') }}</td>
                    {{-- PERUBAHAN: Menggunakan relasi lokasi --}}
                    <td>{{ $receiving->lokasi->nama_gudang }}</td> 
                    <td>
                        {{-- Menambahkan badge agar lebih menarik --}}
                        @if($receiving->status == 'PENDING_QC') <span class="badge badge-warning">PENDING QC</span>
                        @elseif($receiving->status == 'PENDING_PUTAWAY') <span class="badge badge-info">PENDING PUTAWAY</span>
                        @elseif($receiving->status == 'COMPLETED') <span class="badge badge-success">COMPLETED</span>
                        @else {{ $receiving->status }}
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.receivings.show', $receiving->id) }}" class="btn btn-info btn-xs">Lihat</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">Belum ada data penerimaan.</td>
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
        $('#receivings-table').DataTable({
            "responsive": true,
            "order": [[2, "desc"]] // Urutkan berdasarkan tanggal terbaru
        });
    });
</script>
@stop