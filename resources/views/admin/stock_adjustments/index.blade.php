@extends('adminlte::page')

@section('title', 'Daftar Stock Adjustment')

@section('content_header')
    <h1>Stock Adjustment</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Riwayat Penyesuaian Stok</h3>
        <div class="card-tools">
            @can('create-stock-adjustment')
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Baru
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="adj-table" class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Lokasi / Rak</th>
                    <th>Barang</th>
                    <th class="text-center">Tipe</th>
                    <th class="text-center">Jumlah</th>
                    <th>Status</th>
                    <th>Oleh</th>
                    <th width="100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustments as $adj)
                <tr>
                    <td>{{ $adj->created_at->format('d/m/Y') }}</td>
                    <td>
                        {{ $adj->lokasi->nama_lokasi }}<br>
                        <small class="text-muted">{{ $adj->rak->nama_rak ?? '-' }}</small>
                    </td>
                    <td>
                        {{ $adj->barang->part_name }}<br>
                        <small>{{ $adj->barang->part_code }}</small>
                    </td>
                    <td class="text-center">
                        @if($adj->tipe == 'TAMBAH')
                            <span class="badge badge-success"><i class="fas fa-plus"></i> TAMBAH</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-minus"></i> KURANG</span>
                        @endif
                    </td>
                    <td class="text-center font-weight-bold">{{ $adj->jumlah }}</td>
                    <td>
                        @if($adj->status == 'PENDING_APPROVAL')
                            <span class="badge badge-warning">Menunggu</span>
                        @elseif($adj->status == 'APPROVED')
                            <span class="badge badge-primary">Disetujui</span>
                        @else
                            <span class="badge badge-secondary">Ditolak</span>
                        @endif
                    </td>
                    <td><small>{{ $adj->createdBy->nama ?? 'System' }}</small></td>
                    <td>
                        @if($adj->status == 'PENDING_APPROVAL')
                            @can('approve-stock-adjustment', $adj)
                                <form action="{{ route('admin.stock-adjustments.approve', $adj->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Setujui penyesuaian stok ini?')">
                                    @csrf
                                    <button class="btn btn-success btn-xs" title="Approve"><i class="fas fa-check"></i></button>
                                </form>
                                <button class="btn btn-danger btn-xs btn-reject" data-id="{{ $adj->id }}" title="Reject"><i class="fas fa-times"></i></button>
                            @endcan
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Single Modal Reject --}}
<div class="modal fade" id="modal-reject" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form id="form-reject" action="" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-danger">
                <h5 class="modal-title">Tolak Adjustment</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Alasan Penolakan</label>
                    <textarea name="rejection_reason" class="form-control" required rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak</button>
            </div>
        </form>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#adj-table').DataTable({ "responsive": true, "autoWidth": false });

    // Handle Reject Button Click
    $('.btn-reject').click(function() {
        let id = $(this).data('id');
        let url = "{{ url('admin/stock-adjustments') }}/" + id + "/reject";
        $('#form-reject').attr('action', url);
        $('#modal-reject').modal('show');
    });
});
</script>
@stop