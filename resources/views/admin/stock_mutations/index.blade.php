@extends('adminlte::page')

@section('title', 'Mutasi Stok')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Mutasi Stok</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Permintaan Mutasi</h3>
        <div class="card-tools">
            {{-- Menggunakan gate baru --}}
            @can('create-stock-transaction')
            <a href="{{ route('admin.stock-mutations.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat Mutasi Baru</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="mutation-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nomor Mutasi</th>
                    <th>Part</th>
                    <th class="text-center">Jumlah</th>
                    <th>Asal & Tujuan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mutations as $mutation)
                <tr>
                    <td>
                        <strong>{{ $mutation->nomor_mutasi }}</strong><br>
                        <small class="text-muted">{{ $mutation->created_at->format('d M Y') }}</small>
                    </td>
                    <td>
                        {{ $mutation->part->nama_part }}
                        <br>
                        <small class="text-muted">{{ $mutation->part->kode_part }}</small>
                    </td>
                    <td class="text-center">{{ $mutation->jumlah }}</td>
                    <td>
                        {{-- PERUBAHAN: Menggunakan relasi baru --}}
                        <i class="fas fa-arrow-up text-danger"></i> {{ $mutation->lokasiAsal->nama_lokasi }}<br>
                        <i class="fas fa-arrow-down text-success"></i> {{ $mutation->lokasiTujuan->nama_lokasi }}
                    </td>
                    <td class="text-center">
                        @if($mutation->status == 'PENDING_APPROVAL') <span class="badge badge-warning">Menunggu Persetujuan</span>
                        @elseif($mutation->status == 'IN_TRANSIT') <span class="badge badge-info">Dalam Perjalanan</span>
                        @elseif($mutation->status == 'COMPLETED') <span class="badge badge-success">Selesai</span>
                        @elseif($mutation->status == 'REJECTED') <span class="badge badge-danger">Ditolak</span>
                        @else <span class="badge badge-secondary">{{ $mutation->status }}</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.stock-mutations.show', $mutation) }}" class="btn btn-info btn-xs">
                            <i class="fas fa-eye"></i> Detail
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop

@section('js')
<script>
    $(function () {
        $("#mutation-table").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false, "order": [[0, "desc"]]
        });
    });
</script>
@stop
