@extends('adminlte::page')

@section('title', 'Detail Retur Penjualan')

@section('content_header')
    <h1>Detail Retur: {{ $salesReturn->nomor_retur_jual }}</h1>
@stop

@section('content')
<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">Ringkasan Informasi</h3>
        <div class="card-tools">
            <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-default btn-sm">Kembali ke Daftar</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row invoice-info mb-4">
            <div class="col-sm-4 invoice-col">
                <address>
                    <strong>Konsumen:</strong><br>
                    {{ $salesReturn->konsumen->nama_konsumen }}<br>
                    {{ $salesReturn->konsumen->alamat }}
                </address>
            </div>
            <div class="col-sm-4 invoice-col">
                <b>No. Retur:</b> {{ $salesReturn->nomor_retur_jual }}<br>
                <b>Tanggal:</b> {{ $salesReturn->tanggal_retur->format('d/m/Y') }}<br>
                <b>Lokasi Gudang:</b> {{ $salesReturn->lokasi->nama_lokasi }}
            </div>
            <div class="col-sm-4 invoice-col">
                <b>Ref. Faktur:</b> {{ $salesReturn->penjualan->nomor_faktur }}<br>
                <b>Petugas:</b> {{ $salesReturn->createdBy->nama }}<br>
            </div>
        </div>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Part Code</th>
                    <th>Nama Suku Cadang</th>
                    <th class="text-center">Qty Retur</th>
                    <th class="text-right">Harga Jual</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesReturn->details as $detail)
                <tr>
                    <td>{{ $detail->barang->part_code }}</td>
                    <td>{{ $detail->barang->part_name }}</td>
                    <td class="text-center">{{ $detail->qty_retur }}</td>
                    <td class="text-right">{{ number_format($detail->harga_saat_jual, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-right text-uppercase">Total Nilai Retur (Tanpa PPN):</th>
                    <th class="text-right text-primary">Rp {{ number_format($salesReturn->total_retur, 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>

        @if($salesReturn->catatan)
        <div class="mt-3 p-3 bg-light border">
            <strong>Catatan:</strong><br>
            {{ $salesReturn->catatan }}
        </div>
        @endif
    </div>
</div>
@stop