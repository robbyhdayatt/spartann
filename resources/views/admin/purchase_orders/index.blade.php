@extends('adminlte::page')

@section('title', 'Daftar Transaksi PO')

@section('content_header')
    <h1>Daftar Transaksi PO</h1>
@stop

@section('content')
<div class="card card-primary card-outline card-tabs">
    <div class="card-header p-0 pt-1 border-bottom-0">
        <ul class="nav nav-tabs">
            {{-- TAB 1: DEALER REQUEST --}}
            {{-- HIDE KHUSUS UNTUK KEPALA GUDANG (KG) --}}
            @if(!auth()->user()->hasRole('KG'))
            <li class="nav-item">
                <a class="nav-link {{ $type == 'dealer_request' ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index', ['type' => 'dealer_request']) }}">
                    <i class="fas fa-store"></i> Request Dealer (Masuk)
                </a>
            </li>
            @endif

            {{-- TAB 2: SUPPLIER PO --}}
            {{-- TAMPIL UNTUK: AG, KG, SA --}}
            @if(!auth()->user()->hasRole(['SMD', 'SA', 'PIC', 'DEALER']))
            <li class="nav-item">
                <a class="nav-link {{ $type == 'supplier_po' ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index', ['type' => 'supplier_po']) }}">
                    <i class="fas fa-truck"></i> Order ke Supplier (Keluar)
                </a>
            </li>
            @endif
        </ul>
    </div>
    <div class="card-body">
        
        {{-- TOMBOL BUAT BARU --}}
        @can('create-po')
            {{-- LOGIKA: Sembunyikan tombol jika User adalah Orang Gudang (AG/KG) TAPI sedang melihat Tab Request Dealer (Masuk) --}}
            {{-- Karena di tab ini, orang gudang tugasnya hanya Approve, bukan buat baru --}}
            @if( ! (auth()->user()->hasRole(['AG', 'KG']) && $type == 'dealer_request') )
                <div class="mb-2 text-right">
                    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Buat Transaksi Baru
                    </a>
                </div>
            @endif
        @endcan
        
        <table id="po-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nomor PO</th>
                    <th>Tanggal</th>
                    <th>{{ $type == 'supplier_po' ? 'Supplier' : 'Dealer Pengaju' }}</th>
                    <th>Total Item</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseOrders as $po)
                <tr>
                    <td>{{ $po->nomor_po }}</td>
                    <td>{{ $po->tanggal_po->format('d/m/Y') }}</td>
                    <td>
                        @if($po->po_type == 'supplier_po')
                            {{ $po->supplier->nama_supplier ?? '-' }}
                        @else
                            {{ $po->lokasi->nama_lokasi ?? 'Dealer' }}
                        @endif
                    </td>
                    <td>{{ $po->details->count() }}</td>
                    <td><span class="badge {{ $po->status_class }}">{{ $po->status }}</span></td>
                    <td>
                        <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-xs btn-info">Detail</a>
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
    $(document).ready(function() {
        $('#po-table').DataTable({
            "order": [[ 0, "desc" ]]
        });
    });
</script>
@stop