@extends('adminlte::page')

@section('title', 'Detail Purchase Order')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Detail Purchase Order: <strong>{{ $purchaseOrder->nomor_po }}</strong></h1>
        <div class="no-print">
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
    </div>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header no-print text-right">
        {{-- Tombol Cetak PDF ditaruh di sini untuk akses cepat --}}
        <a href="{{ route('admin.purchase-orders.pdf', $purchaseOrder) }}" target="_blank" class="btn btn-default shadow-sm">
            <i class="fas fa-print mr-1"></i> Cetak / Download PDF
        </a>
    </div>

    <div class="card-body">
        {{-- Tampilkan Alasan Penolakan JIKA ADA --}}
        @if($purchaseOrder->status === 'REJECTED' && $purchaseOrder->rejection_reason)
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <h5><i class="icon fas fa-ban"></i> Ditolak!</h5>
            <strong>Alasan:</strong> {{ $purchaseOrder->rejection_reason }}
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <h5><i class="icon fas fa-check"></i> Sukses!</h5>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <h5><i class="icon fas fa-ban"></i> Error!</h5>
            {{ session('error') }}
        </div>
        @endif

        {{-- Menggunakan file po_content.blade.php untuk menampilkan detail & tombol aksi --}}
        @include('admin.purchase_orders.po_content', ['purchaseOrder' => $purchaseOrder])
    </div>
</div>

{{-- MODAL UNTUK ALASAN PENOLAKAN --}}
<div class="modal fade no-print" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="{{ route('admin.purchase-orders.reject', $purchaseOrder) }}" method="POST">
        @csrf
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="rejectModalLabel"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Penolakan</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="rejection_reason">Mohon berikan alasan mengapa PO ini ditolak:</label>
            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required placeholder="Contoh: Stok barang kosong / Harga tidak sesuai..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Tolak PO</button>
        </div>
      </form>
    </div>
  </div>
</div>
@stop

@push('css')
<style>
    /* CSS untuk menyembunyikan elemen saat mencetak dari browser (Ctrl+P) */
    @media print {
        .no-print, .main-footer, .content-header {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
@endpush