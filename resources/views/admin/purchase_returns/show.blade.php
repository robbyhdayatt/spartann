@extends('adminlte::page')

@section('title', 'Detail Retur Pembelian')

@section('content_header')
    <h1>Detail Retur Pembelian: {{ $purchaseReturn->nomor_retur }}</h1>
@stop

@section('content')
<div class="card card-outline card-danger">
    <div class="card-header">
        <h3 class="card-title">Informasi Retur</h3>
        <div class="card-tools">
            <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-default btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button onclick="window.print()" class="btn btn-default btn-sm ml-1">
                <i class="fas fa-print"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row invoice-info mb-4">
            <div class="col-sm-4 invoice-col">
                <label>Dari (Pihak Gudang):</label>
                <address>
                    <strong>SPARTAN SYSTEM</strong><br>
                    Lokasi: {{ $purchaseReturn->receiving->lokasi->nama_lokasi ?? 'N/A' }}<br>
                    Operator: {{ $purchaseReturn->createdBy->nama ?? 'System' }}
                </address>
            </div>
            <div class="col-sm-4 invoice-col">
                <label>Kepada (Supplier):</label>
                <address>
                    <strong>{{ $purchaseReturn->supplier->nama_supplier ?? 'Umum' }}</strong><br>
                    {{ $purchaseReturn->supplier->alamat ?? '' }}<br>
                    Telepon: {{ $purchaseReturn->supplier->telepon ?? '-' }}
                </address>
            </div>
            <div class="col-sm-4 invoice-col">
                <b>Nomor Retur:</b> {{ $purchaseReturn->nomor_retur }}<br>
                <b>Ref. Penerimaan:</b> {{ $purchaseReturn->receiving->nomor_penerimaan }}<br>
                <b>Tanggal Retur:</b> {{ $purchaseReturn->tanggal_retur->format('d F Y') }}<br>
            </div>
        </div>

        <div class="row">
            <div class="col-12 table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="bg-danger">
                        <tr>
                            <th>No</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Alasan Retur</th>
                            <th class="text-center">Qty Retur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseReturn->details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $detail->barang->part_code }}</td>
                            <td>{{ $detail->barang->part_name }}</td>
                            <td>{{ $detail->alasan ?? '-' }}</td>
                            <td class="text-center font-weight-bold">{{ $detail->qty_retur }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($purchaseReturn->catatan)
        <div class="row mt-4">
            <div class="col-12">
                <p class="lead text-muted" style="font-size: 1rem;">Catatan:</p>
                <p class="text-muted well well-sm shadow-none" style="margin-top: 10px;">
                    {{ $purchaseReturn->catatan }}
                </p>
            </div>
        </div>
        @endif
    </div>
</div>
@stop