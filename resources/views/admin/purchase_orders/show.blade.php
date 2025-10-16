@extends('adminlte::page')

@section('title', 'Detail Purchase Order')

@section('content_header')
    <h1>Detail Purchase Order: {{ $purchaseOrder->nomor_po }}</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header no-print">
        <div class="d-flex justify-content-between">
            <div>
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
            <div>
                @if($purchaseOrder->status === 'PENDING_APPROVAL')
                    @can('approve-po', $purchaseOrder)
                    <form action="{{ route('admin.purchase-orders.approve', $purchaseOrder) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="far fa-credit-card"></i> Setujui</button>
                    </form>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                    @endcan
                @endif
                <a href="{{ route('admin.purchase-orders.pdf', $purchaseOrder) }}" target="_blank" class="btn btn-primary"><i class="fas fa-download"></i> Generate PDF</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        {{-- Tampilkan Alasan Penolakan JIKA ADA --}}
        @if($purchaseOrder->status === 'REJECTED' && $purchaseOrder->rejection_reason)
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger">
                    <h5><i class="icon fas fa-ban"></i> Alasan Penolakan</h5>
                    {{ $purchaseOrder->rejection_reason }}
                </div>
            </div>
        </div>
        @endif

        {{-- Menggunakan file po_content.blade.php untuk menampilkan detail --}}
        @include('admin.purchase_orders.po_content', ['purchaseOrder' => $purchaseOrder])
    </div>
</div>

{{-- MODAL UNTUK ALASAN PENOLAKAN --}}
<div class="modal fade no-print" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="{{ route('admin.purchase-orders.reject', $purchaseOrder) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="rejectModalLabel">Alasan Penolakan PO</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="rejection_reason">Mohon berikan alasan mengapa PO ini ditolak:</label>
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

@push('css')
<style>
    /* CSS untuk menyembunyikan elemen saat mencetak dari browser */
    @media print {
        .no-print {
            display: none !important;
        }
        .main-footer, .content-header {
            display: none !important;
        }
    }
</style>
@endpush