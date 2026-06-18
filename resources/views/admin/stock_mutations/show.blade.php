@extends('adminlte::page')

@section('title', 'Detail Mutasi Stok')

@section('content_header')
    <h1>Detail Mutasi Stok: {{ $stockMutation->nomor_mutasi }}</h1>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible shadow-sm">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <i class="icon fas fa-check"></i> {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible shadow-sm">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <i class="icon fas fa-ban"></i> {{ session('error') }}
    </div>
@endif

<div class="card card-outline card-primary shadow-sm">
    <div class="card-header">
        <h3 class="card-title">Informasi Mutasi</h3>
        <div class="card-tools">
            {{-- Tombol Approval hanya jika status PENDING --}}
            @if($stockMutation->status === 'PENDING_APPROVAL')
                @can('approve-stock-transaction', $stockMutation)
                    <form action="{{ route('admin.stock-mutations.approve', $stockMutation->id) }}" method="POST" class="d-inline" onsubmit="return confirm('PERINGATAN: Menyetujui ini akan memotong stok fisik dari gudang asal. Lanjutkan?');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm shadow-sm">
                            <i class="fas fa-check mr-1"></i> Setujui & Potong Stok
                        </button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm shadow-sm" data-toggle="modal" data-target="#rejectModal">
                        <i class="fas fa-times mr-1"></i> Tolak
                    </button>
                @endcan
            @endif

            {{-- TOMBOL TERIMA BARANG jika status IN_TRANSIT --}}
            @if($stockMutation->status === 'IN_TRANSIT')
                @can('receive-stock-transaction', $stockMutation)
                    <form action="{{ route('admin.stock-mutations.receive', $stockMutation->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah barang fisik sudah Anda terima dan diperiksa? Ini akan menambah stok di gudang Anda!');">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm shadow-sm">
                            <i class="fas fa-box-open mr-1"></i> Konfirmasi Terima Barang
                        </button>
                    </form>
                @endcan
            @endif

            <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-default btn-sm ml-2 shadow-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            {{-- Kolom Kiri --}}
            <div class="col-md-6">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th style="width: 35%">Nomor Dokumen</th>
                        <td>: <strong>{{ $stockMutation->nomor_mutasi }}</strong></td>
                    </tr>
                    <tr>
                        <th>Tanggal Dibuat</th>
                        <td>: {{ $stockMutation->created_at->format('d F Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Status Mutasi</th>
                        <td>: 
                            @if($stockMutation->status == 'PENDING_APPROVAL') <span class="badge badge-warning">Menunggu Persetujuan</span>
                            @elseif($stockMutation->status == 'IN_TRANSIT') <span class="badge badge-info"><i class="fas fa-shipping-fast"></i> Dalam Perjalanan</span>
                            @elseif($stockMutation->status == 'COMPLETED') <span class="badge badge-success"><i class="fas fa-check-circle"></i> Selesai (Diterima)</span>
                            @elseif($stockMutation->status == 'REJECTED') <span class="badge badge-danger">Ditolak</span>
                            @else <span class="badge badge-secondary">{{ $stockMutation->status }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Dibuat Oleh</th>
                        <td>: {{ $stockMutation->createdBy->nama ?? 'System' }}</td>
                    </tr>
                </table>
            </div>

            {{-- Kolom Kanan (Rute) --}}
            <div class="col-md-6">
                <div class="callout callout-info bg-light">
                    <h5><i class="fas fa-route text-info"></i> Rute Mutasi Fisik</h5>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-center w-50">
                            <strong class="d-block text-danger">Gudang Asal <i class="fas fa-arrow-up"></i></strong>
                            <h5 class="mb-0 mt-1 font-weight-bold">{{ $stockMutation->lokasiAsal->nama_lokasi }}</h5>
                            <small class="text-muted">{{ $stockMutation->lokasiAsal->kode_lokasi }}</small>
                        </div>
                        <div class="text-center px-3">
                            <i class="fas fa-truck-moving fa-2x text-secondary {{ $stockMutation->status == 'IN_TRANSIT' ? 'text-info' : '' }}"></i>
                        </div>
                        <div class="text-center w-50">
                            <strong class="d-block text-success">Gudang Tujuan <i class="fas fa-arrow-down"></i></strong>
                            <h5 class="mb-0 mt-1 font-weight-bold">{{ $stockMutation->lokasiTujuan->nama_lokasi }}</h5>
                            <small class="text-muted">{{ $stockMutation->lokasiTujuan->kode_lokasi }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- Detail Barang --}}
        <h5 class="mt-4 text-primary"><i class="fas fa-box mr-2"></i>Item yang Dipindahkan</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped shadow-sm">
                <thead class="bg-light">
                    <tr>
                        <th>Kode Part</th>
                        <th>Nama Barang</th>
                        <th class="text-center">Jumlah Pcs</th>
                        <th>Keterangan / Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="align-middle"><code>{{ $stockMutation->barang->part_code }}</code></td>
                        <td class="align-middle font-weight-bold">{{ $stockMutation->barang->part_name }}</td>
                        <td class="text-center font-weight-bold align-middle text-primary" style="font-size: 1.3em">{{ $stockMutation->jumlah }}</td>
                        <td class="align-middle">{{ $stockMutation->keterangan ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- History Log --}}
        <div class="row mt-4">
            {{-- Jika Ditolak --}}
            @if($stockMutation->status === 'REJECTED' && $stockMutation->rejection_reason)
                <div class="col-12">
                    <div class="alert alert-danger">
                        <h5><i class="icon fas fa-ban"></i> Alasan Penolakan:</h5>
                        {{ $stockMutation->rejection_reason }}
                        <div class="mt-2 small text-white-50">
                            Ditolak oleh: {{ $stockMutation->approvedBy->nama ?? '-' }} pada {{ $stockMutation->approved_at->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>
            @endif
            
            {{-- Jika Disetujui (Log Approval) --}}
            @if($stockMutation->approved_at)
                <div class="col-md-6">
                    <div class="alert alert-warning text-dark border-warning bg-light">
                        <i class="icon fas fa-clipboard-check text-warning"></i> 
                        <strong>Disetujui Oleh:</strong> {{ $stockMutation->approvedBy->nama ?? '-' }}<br>
                        <small class="text-muted"><i class="far fa-clock"></i> Waktu: {{ $stockMutation->approved_at->format('d M Y, H:i') }}</small>
                    </div>
                </div>
            @endif

            {{-- Jika Diterima (Log Receive) --}}
            @if($stockMutation->received_at)
                <div class="col-md-6">
                    <div class="alert alert-success border-success bg-light text-dark">
                        <i class="icon fas fa-box-open text-success"></i> 
                        <strong>Diterima Oleh:</strong> {{ $stockMutation->receivedBy->nama ?? '-' }}<br>
                        <small class="text-muted"><i class="far fa-clock"></i> Waktu: {{ $stockMutation->received_at->format('d M Y, H:i') }}</small>
                    </div>
                </div>
            @endif
        </div>
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
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Kunci Penolakan</button>
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