@extends('adminlte::page')

@section('title', 'Daftar Purchase Order')

@section('content_header')
    <h1>Daftar Purchase Order</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Data Purchase Order</h3>
        <div class="card-tools">
            @can('create-po')
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat PO Baru</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <table id="po-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nomor PO</th>
                    <th>Sumber / Supplier</th> {{-- Judul Kolom Disesuaikan --}}
                    <th class="text-right">Total</th>
                    <th class="text-center">Status</th>
                    <th>Dibuat Oleh</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseOrders as $po)
                <tr>
                    <td>
                        <strong>{{ $po->nomor_po }}</strong><br>
                        <small class="text-muted">{{ $po->tanggal_po->format('d M Y') }}</small>
                        @if($po->request_group_id)
                             <br><span class="badge badge-light text-xs">{{ $po->request_group_id }}</span>
                        @endif
                    </td>

                    {{-- PERBAIKAN DISINI: Cek Supplier vs Internal --}}
                    <td>
                        @if($po->supplier)
                            <i class="fas fa-truck text-primary"></i> {{ $po->supplier->nama_supplier }}
                        @elseif($po->sumberLokasi)
                            <i class="fas fa-warehouse text-success"></i> {{ $po->sumberLokasi->nama_lokasi }} <br>
                            <small class="text-muted">(Internal Transfer)</small>
                        @else
                            <span class="text-danger">Unknown Source</span>
                        @endif
                    </td>
                    {{-- ------------------------------------------ --}}

                    <td class="text-right">{{ 'Rp ' . number_format($po->total_amount, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge {{ $po->status_class }}">{{ $po->status_badge }}</span>
                    </td>
                    <td>
                        {{ $po->createdBy->nama ?? 'N/A' }}<br>
                        <small class="text-muted">Tujuan: {{ $po->lokasi->nama_lokasi ?? '' }}</small>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.purchase-orders.show', $po) }}" class="btn btn-info btn-xs">
                            <i class="fas fa-eye"></i> Detail
                        </a>
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
        $(function () {
            $("#po-table").DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "order": [[0, "desc"]],
                "buttons": ["copy", "csv", "excel", "pdf", "print"]
            }).buttons().container().appendTo('#po-table_wrapper .col-md-6:eq(0)');
        });
    script>
@stop
