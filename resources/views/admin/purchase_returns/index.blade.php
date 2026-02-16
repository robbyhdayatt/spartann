@extends('adminlte::page')

@section('title', 'Daftar Retur Pembelian')
@section('plugins.Datatables', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Retur Pembelian</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-danger shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Riwayat Pengembalian Barang</h3>
        <div class="card-tools">
            <a href="{{ route('admin.purchase-returns.create') }}" class="btn btn-danger btn-sm shadow-sm">
                <i class="fas fa-plus mr-1"></i> Buat Retur Baru
            </a>
        </div>
    </div>
    <div class="card-body">
        <table id="purchase_returns-table" class="table table-bordered table-striped table-hover">
            <thead class="bg-gradient-light">
                <tr>
                    <th width="15%">No. Retur</th>
                    <th width="12%">Tanggal</th>
                    <th width="15%">No. Penerimaan</th>
                    <th>Supplier / Tujuan</th>
                    <th width="15%">Dibuat Oleh</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $return)
                <tr>
                    <td class="font-weight-bold text-danger align-middle">{{ $return->nomor_retur }}</td>
                    <td class="align-middle">{{ $return->tanggal_retur->format('d/m/Y') }}</td>
                    <td class="align-middle">
                        <a href="{{ route('admin.receivings.show', $return->receiving_id) }}" class="text-dark">
                            {{ $return->receiving->nomor_penerimaan ?? '-' }}
                        </a>
                    </td>
                    <td class="align-middle">
                        <i class="fas fa-truck text-muted mr-1"></i>
                        {{ $return->supplier->nama_supplier ?? 'Umum' }}
                    </td>
                    <td class="align-middle text-sm text-muted">
                        {{ $return->createdBy->nama ?? 'System' }}
                    </td>
                    <td class="text-center align-middle">
                        <a href="{{ route('admin.purchase-returns.show', $return->id) }}" class="btn btn-info btn-xs shadow-sm" title="Lihat Detail">
                            <i class="fas fa-eye"></i> Detail
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
        $('#purchase_returns-table').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[ 0, "desc" ]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            }
        });
    });
</script>
@stop