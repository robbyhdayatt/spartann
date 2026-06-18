@extends('adminlte::page')

@section('title', 'Daftar Stock Adjustment')
@section('plugins.Datatables', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Stock Adjustment</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-primary shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Riwayat Penyesuaian Stok</h3>
        <div class="card-tools">
            @can('create-stock-adjustment')
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle mr-1"></i> Buat Adjustment Baru
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>{{ session('success') }}</x-adminlte-alert>
        @endif
        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <table id="adj-table" class="table table-bordered table-striped table-hover">
            <thead class="bg-gradient-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Lokasi / Rak</th>
                    <th width="25%">Barang</th>
                    <th class="text-center">Tipe</th>
                    <th class="text-center">Jumlah</th>
                    <th class="text-center">Status</th>
                    <th>Oleh</th>
                    <th width="100px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustments as $adj)
                <tr>
                    {{-- [MODIFIKASI] Tambah atribut data-sort agar DataTables mengurutkan berdasarkan format waktu baku, bukan teks visual --}}
                    <td class="align-middle" data-sort="{{ $adj->created_at->format('Y-m-d H:i:s') }}">
                        <div class="font-weight-bold">{{ $adj->created_at->format('d/m/Y') }}</div>
                        <small class="text-muted">{{ $adj->created_at->format('H:i') }}</small>
                    </td>
                    <td class="align-middle">
                        <strong>{{ $adj->lokasi->nama_lokasi }}</strong><br>
                        <small class="text-muted"><i class="fas fa-th mr-1"></i> {{ $adj->rak->kode_rak ?? '-' }}</small>
                    </td>
                    <td class="align-middle">
                        <div class="font-weight-bold">{{ $adj->barang->part_name }}</div>
                        <div class="text-xs text-muted">{{ $adj->barang->part_code }}</div>
                        <div class="text-xs font-italic text-secondary mt-1">"{{ Str::limit($adj->alasan, 50) }}"</div>
                    </td>
                    <td class="text-center align-middle">
                        @if($adj->tipe == 'TAMBAH')
                            <span class="badge badge-success"><i class="fas fa-plus mr-1"></i> TAMBAH</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-minus mr-1"></i> KURANG</span>
                        @endif
                    </td>
                    <td class="text-center align-middle font-weight-bold" style="font-size: 1.1em">{{ $adj->jumlah }}</td>
                    <td class="text-center align-middle">
                        @if($adj->status == 'PENDING_APPROVAL')
                            <span class="badge badge-warning">Menunggu</span>
                        @elseif($adj->status == 'APPROVED')
                            <span class="badge badge-primary">Disetujui</span>
                        @else
                            <span class="badge badge-secondary" title="{{ $adj->rejection_reason ?? 'Tanpa keterangan' }}">Ditolak</span>
                        @endif
                    </td>
                    {{-- [MODIFIKASI] Menambahkan info siapa yang membuat dan menyetujui --}}
                    <td class="align-middle text-sm">
                        <div><i class="fas fa-user-edit text-muted mr-1" title="Dibuat oleh"></i> {{ $adj->createdBy->nama ?? $adj->createdBy->username ?? 'System' }}</div>
                        @if($adj->status == 'APPROVED')
                            <div class="mt-1 text-success"><i class="fas fa-check-double mr-1" title="Disetujui oleh"></i> {{ $adj->approvedBy->nama ?? $adj->approvedBy->username ?? 'Unknown' }}</div>
                        @elseif($adj->status == 'REJECTED')
                            <div class="mt-1 text-danger"><i class="fas fa-times mr-1" title="Ditolak oleh"></i> {{ $adj->approvedBy->nama ?? $adj->approvedBy->username ?? 'Unknown' }}</div>
                        @endif
                    </td>
                    <td class="align-middle text-center">
                        @if($adj->status == 'PENDING_APPROVAL')
                            @can('approve-stock-adjustment', $adj)
                                <div class="btn-group">
                                    <form action="{{ route('admin.stock-adjustments.approve', $adj->id) }}" method="POST" onsubmit="return confirm('Setujui penyesuaian stok ini?')">
                                        @csrf
                                        <button class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <button class="btn btn-danger btn-sm btn-reject ml-1" data-id="{{ $adj->id }}" title="Reject"><i class="fas fa-times"></i></button>
                                </div>
                            @endcan
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Reject --}}
<div class="modal fade" id="modal-reject" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form id="form-reject" action="" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-danger">
                <h5 class="modal-title">Tolak Adjustment</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Alasan Penolakan</label>
                    <textarea name="rejection_reason" class="form-control" required rows="3" placeholder="Kenapa ditolak?"></textarea>
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
    $('#adj-table').DataTable({ 
        "responsive": true, 
        "autoWidth": false,
        "order": [[ 0, "desc" ]] 
    });

    $('.btn-reject').click(function() {
        let id = $(this).data('id');
        let url = "{{ url('admin/stock-adjustments') }}/" + id + "/reject";
        $('#form-reject').attr('action', url);
        $('#modal-reject').modal('show');
    });
});
</script>
@stop