@extends('adminlte::page')

@section('title', 'Daftar Transaksi PO')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Daftar Transaksi PO</h1>
        </div>
    </div>
@stop

@section('content')
<div class="card card-primary card-outline card-tabs shadow-sm">
    <div class="card-header p-0 pt-1 border-bottom-0">
        <ul class="nav nav-tabs" role="tablist">
            
            {{-- TAB 1: DEALER REQUEST (Internal Distribution) --}}
            {{-- VIEW: PUSAT (IMS, ASD, ACC), DEALER (PC, KC), SA/PIC --}}
            {{-- HIDE: GUDANG (AG, KG) -> Mereka handle ini di menu Receiving --}}
            @if(auth()->user()->isGlobal() || auth()->user()->isPusat() || auth()->user()->isDealer())
            <li class="nav-item">
                <a class="nav-link {{ $type == 'dealer_request' ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index', ['type' => 'dealer_request']) }}">
                    <i class="fas fa-store mr-1"></i> Request Dealer (Internal)
                </a>
            </li>
            @endif

            {{-- TAB 2: SUPPLIER PO (External Purchase) --}}
            {{-- VIEW: GUDANG (AG, KG), SA/PIC --}}
            {{-- HIDE: PUSAT (IMS, ASD), DEALER --}}
            @if(auth()->user()->isGlobal() || auth()->user()->isGudang())
            <li class="nav-item">
                <a class="nav-link {{ $type == 'supplier_po' ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index', ['type' => 'supplier_po']) }}">
                    <i class="fas fa-truck mr-1"></i> Order ke Supplier (Eksternal)
                </a>
            </li>
            @endif
        </ul>
    </div>
    <div class="card-body">
        
        {{-- TOMBOL BUAT BARU --}}
        {{-- Gate 'create-po' sudah diperbaiki di AuthServiceProvider --}}
        @can('create-po')
            <div class="mb-3 text-right">
                <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus-circle mr-1"></i> Buat Transaksi Baru
                </a>
            </div>
        @endcan
        
        <div class="table-responsive">
            <table id="po-table" class="table table-bordered table-striped table-hover">
                <thead class="bg-gradient-dark">
                    <tr>
                        <th style="width: 15%">Nomor PO</th>
                        <th style="width: 12%">Tanggal</th>
                        <th>{{ $type == 'supplier_po' ? 'Supplier' : 'Dealer Pengaju' }}</th>
                        <th style="width: 15%">Dibuat Oleh</th>
                        <th style="width: 10%" class="text-center">Item</th>
                        <th style="width: 12%" class="text-center">Status</th>
                        <th style="width: 10%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseOrders as $po)
                    <tr>
                        <td class="font-weight-bold align-middle">{{ $po->nomor_po }}</td>
                        <td class="align-middle">{{ $po->tanggal_po->format('d/m/Y') }}</td>
                        <td class="align-middle">
                            @if($po->po_type == 'supplier_po')
                                <i class="fas fa-building text-secondary mr-1"></i> {{ $po->supplier->nama_supplier ?? '-' }}
                            @else
                                <i class="fas fa-store text-info mr-1"></i> {{ $po->lokasi->nama_lokasi ?? 'Dealer' }}
                            @endif
                        </td>
                        <td class="align-middle text-sm text-muted">
                            {{ $po->createdBy->nama ?? 'Admin' }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-info badge-pill px-3">{{ $po->details->count() }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $colors = [
                                    'PENDING_APPROVAL' => 'warning',
                                    'APPROVED'         => 'primary',
                                    'PARTIALLY_RECEIVED' => 'info',
                                    'FULLY_RECEIVED'   => 'success',
                                    'REJECTED'         => 'danger',
                                ];
                                $statusColor = $colors[$po->status] ?? 'secondary';
                            @endphp
                            <span class="badge badge-{{ $statusColor }}">{{ str_replace('_', ' ', $po->status) }}</span>
                        </td>
                        <td class="align-middle text-center">
                            <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#po-table').DataTable({
            "order": [[ 0, "desc" ]],
            "responsive": true,
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" }
        });
    });
</script>
@stop