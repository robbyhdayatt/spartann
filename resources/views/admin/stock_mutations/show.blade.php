@extends('adminlte::page')

@section('title', 'Detail Mutasi Stok')

@section('content_header')
    <h1>Detail Mutasi Stok: {{ $stockMutation->nomor_mutasi }}</h1>
@stop

@section('content')
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4>Informasi Mutasi</h4>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><b>Nomor Mutasi:</b> {{ $stockMutation->nomor_mutasi }}</li>
                    <li class="list-group-item"><b>Status:</b>
                        @if($stockMutation->status == 'PENDING_APPROVAL') <span class="badge badge-warning">Menunggu Persetujuan</span>
                        @elseif($stockMutation->status == 'IN_TRANSIT') <span class="badge badge-info">Dalam Perjalanan</span>
                        @elseif($stockMutation->status == 'COMPLETED') <span class="badge badge-success">Selesai</span>
                        @elseif($stockMutation->status == 'REJECTED') <span class="badge badge-danger">Ditolak</span>
                        @else <span class="badge badge-secondary">{{ $stockMutation->status }}</span>
                        @endif
                    </li>
                    <li class="list-group-item"><b>Part:</b> {{ $stockMutation->part->nama_part }} ({{$stockMutation->part->kode_part}})</li>
                    <li class="list-group-item"><b>Jumlah:</b> {{ $stockMutation->jumlah }} {{ $stockMutation->part->satuan }}</li>
                    <li class="list-group-item"><b>Keterangan:</b> {{ $stockMutation->keterangan ?? '-' }}</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>Detail Lokasi</h4>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><b>Lokasi Asal:</b> {{ $stockMutation->lokasiAsal->nama_gudang }}</li>
                    <li class="list-group-item"><b>Rak Asal:</b>
                        @if ($stockMutation->rakAsal)
                            {{ $stockMutation->rakAsal->nama_rak }} ({{ $stockMutation->rakAsal->kode_rak }})
                        @else
                            <span class="text-muted">Ditentukan saat approval (FIFO)</span>
                        @endif
                    </li>
                    <li class="list-group-item"><b>Lokasi Tujuan:</b> {{ $stockMutation->lokasiTujuan->nama_gudang }}</li>
                    <li class="list-group-item"><b>Rak Tujuan:</b>
                        @if($stockMutation->rakTujuan)
                            {{ $stockMutation->rakTujuan->nama_rak }} ({{ $stockMutation->rakTujuan->kode_rak }})
                        @else
                            <span class="text-muted">Belum Diterima</span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="row">
             <div class="col-md-6">
                <h4>Informasi Pembuat & Persetujuan</h4>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><b>Dibuat Oleh:</b> {{ $stockMutation->createdBy->nama ?? 'N/A' }}</li>
                    <li class="list-group-item"><b>Tanggal Dibuat:</b> {{ $stockMutation->created_at->format('d M Y, H:i') }}</li>
                    <li class="list-group-item"><b>Disetujui/Ditolak Oleh:</b> {{ optional($stockMutation->approvedBy)->nama ?? 'N/A' }}</li>
                    <li class="list-group-item"><b>Tanggal Proses:</b> {{ $stockMutation->approved_at ? $stockMutation->approved_at->format('d M Y, H:i') : 'N/A' }}</li>
                </ul>
            </div>
             <div class="col-md-6">
                <h4>Informasi Penerimaan</h4>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><b>Diterima Oleh:</b> {{ optional($stockMutation->receivedBy)->nama ?? 'N/A' }}</li>
                    <li class="list-group-item"><b>Tanggal Diterima:</b> {{ $stockMutation->received_at ? $stockMutation->received_at->format('d M Y, H:i') : 'N/A' }}</li>
                </ul>
            </div>
        </div>

        @if($stockMutation->status === 'REJECTED' && $stockMutation->rejection_reason)
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-danger">
                    <h5><i class="icon fas fa-ban"></i> Alasan Penolakan</h5>
                    {{ $stockMutation->rejection_reason }}
                </div>
            </div>
        </div>
        @endif

    </div>
    <div class="card-footer">
        @if($stockMutation->status === 'PENDING_APPROVAL')
            @can('approve-stock-transaction', $stockMutation)
                <form action="{{ route('admin.stock-mutations.approve', ['stock_mutation' => $stockMutation->id]) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Setujui & Kirim</button>
                </form>
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectMutationModal">
                    <i class="fas fa-times"></i> Tolak
                </button>
            @endcan
        @endif
        <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-secondary">Kembali</a>
    </div>
</div>

{{-- MODAL UNTUK ALASAN PENOLAKAN --}}
<div class="modal fade" id="rejectMutationModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="{{ route('admin.stock-mutations.reject', ['stock_mutation' => $stockMutation->id]) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="rejectModalLabel">Alasan Penolakan Mutasi</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          {{-- PERBAIKAN: Menambahkan blok untuk menampilkan error di dalam modal --}}
          @if ($errors->has('rejection_reason'))
            <div class="alert alert-danger">
                {{ $errors->first('rejection_reason') }}
            </div>
          @endif
          <div class="form-group">
            <label for="rejection_reason">Mohon berikan alasan mengapa mutasi ini ditolak (min. 10 karakter):</label>
            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Submit Penolakan</button>
        </div>
      </form>
    </div>
  </div>
</div>
@stop

{{-- PERBAIKAN: Menambahkan JavaScript untuk membuka kembali modal jika ada error --}}
@section('js')
<script>
    @if ($errors->has('rejection_reason'))
        $(document).ready(function() {
            $('#rejectMutationModal').modal('show');
        });
    @endif
</script>
@stop
