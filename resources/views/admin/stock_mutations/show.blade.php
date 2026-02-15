@extends('adminlte::page')

@section('title', 'Detail Mutasi Stok')

@section('content_header')
    <h1>Detail Mutasi Stok: {{ $stockMutation->nomor_mutasi }}</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Informasi Mutasi</h3>
        <div class="card-tools">
            {{-- Tombol Approval hanya jika status PENDING --}}
            @if($stockMutation->status === 'PENDING_APPROVAL')
                @can('approve-stock-transaction', $stockMutation)
                    <form action="{{ route('admin.stock-mutations.approve', $stockMutation->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin menyetujui mutasi ini? Stok akan dipotong dari lokasi asal.');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-check mr-1"></i> Setujui
                        </button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#rejectModal">
                        <i class="fas fa-times mr-1"></i> Tolak
                    </button>
                @endcan
            @endif
            <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-default btn-sm ml-2">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            {{-- Kolom Kiri --}}
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%">Nomor Dokumen</th>
                        <td>: <strong>{{ $stockMutation->nomor_mutasi }}</strong></td>
                    </tr>
                    <tr>
                        <th>Tanggal Dibuat</th>
                        <td>: {{ $stockMutation->created_at->format('d F Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>: 
                            @if($stockMutation->status == 'PENDING_APPROVAL') <span class="badge badge-warning">Menunggu Persetujuan</span>
                            @elseif($stockMutation->status == 'IN_TRANSIT') <span class="badge badge-info"><i class="fas fa-shipping-fast"></i> Dalam Perjalanan</span>
                            @elseif($stockMutation->status == 'COMPLETED') <span class="badge badge-success">Diterima / Selesai</span>
                            @elseif($stockMutation->status == 'REJECTED') <span class="badge badge-danger">Ditolak</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Pemohon</th>
                        <td>: {{ $stockMutation->createdBy->nama ?? 'System' }}</td>
                    </tr>
                </table>
            </div>

            {{-- Kolom Kanan --}}
            <div class="col-md-6">
                <div class="callout callout-info">
                    <h5><i class="fas fa-route text-info"></i> Rute Mutasi</h5>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-center">
                            <strong class="d-block text-muted">Asal</strong>
                            <h4>{{ $stockMutation->lokasiAsal->nama_lokasi }}</h4>
                            <small>{{ $stockMutation->lokasiAsal->kode_lokasi }}</small>
                        </div>
                        <i class="fas fa-arrow-right fa-2x text-muted"></i>
                        <div class="text-center">
                            <strong class="d-block text-muted">Tujuan</strong>
                            <h4>{{ $stockMutation->lokasiTujuan->nama_lokasi }}</h4>
                            <small>{{ $stockMutation->lokasiTujuan->kode_lokasi }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- Detail Barang --}}
        <h5 class="mt-4 text-primary"><i class="fas fa-box mr-2"></i>Detail Barang</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="bg-light">
                    <tr>
                        <th>Kode Part</th>
                        <th>Nama Barang</th>
                        <th class="text-center">Jumlah Diminta</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $stockMutation->barang->part_code }}</td>
                        <td>{{ $stockMutation->barang->part_name }}</td>
                        <td class="text-center font-weight-bold" style="font-size: 1.2em">{{ $stockMutation->jumlah }}</td>
                        <td>{{ $stockMutation->keterangan ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Jika Ditolak --}}
        @if($stockMutation->status === 'REJECTED' && $stockMutation->rejection_reason)
            <div class="alert alert-danger mt-4">
                <h5><i class="icon fas fa-ban"></i> Alasan Penolakan:</h5>
                {{ $stockMutation->rejection_reason }}
                <div class="mt-2 small text-white-50">
                    Ditolak oleh: {{ $stockMutation->approvedBy->nama ?? '-' }} pada {{ $stockMutation->approved_at->format('d/m/Y H:i') }}
                </div>
            </div>
        @endif
        
        {{-- Jika Disetujui --}}
        @if($stockMutation->approved_at)
             <div class="alert alert-success mt-4">
                <i class="icon fas fa-check"></i> 
                Disetujui oleh <strong>{{ $stockMutation->approvedBy->nama ?? '-' }}</strong> 
                pada {{ $stockMutation->approved_at->format('d/m/Y H:i') }}
            </div>
        @endif

    </div>
</div>

{{-- Modal Tolak --}}
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.stock-mutations.reject', $stockMutation->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Permintaan Mutasi</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Jelaskan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
    @if ($errors->has('rejection_reason'))
        $('#rejectModal').modal('show');
    @endif
</script>
@stop