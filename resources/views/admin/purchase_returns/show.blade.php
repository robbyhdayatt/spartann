@extends('adminlte::page')

@section('title', 'Detail Retur Pembelian')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Detail Retur <small class="text-muted">{{ $purchaseReturn->nomor_retur }}</small></h1>
        <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>
@stop

@section('content')
<div class="card card-outline card-danger shadow-sm">
    <div class="card-header">
        <h3 class="card-title">Informasi Retur</h3>
        <div class="card-tools">
            {{-- Tombol Download PDF --}}
            <a href="{{ route('admin.purchase-returns.pdf', $purchaseReturn->id) }}" target="_blank" class="btn btn-danger btn-sm">
                <i class="fas fa-file-pdf mr-1"></i> Download PDF
            </a>
        </div>
    </div>
    
    <div class="card-body">
        {{-- Header Informasi (Pengirim & Penerima) --}}
        <div class="row invoice-info mb-4">
            <div class="col-sm-4 invoice-col">
                <strong class="text-secondary">Dari (Gudang Pengirim):</strong>
                <address class="mt-2">
                    <strong>{{ $purchaseReturn->receiving->lokasi->nama_lokasi ?? 'Gudang Utama' }}</strong><br>
                    {{ $purchaseReturn->receiving->lokasi->alamat ?? '-' }}<br>
                    Operator: {{ $purchaseReturn->createdBy->nama ?? 'Admin' }}
                </address>
            </div>
            <div class="col-sm-4 invoice-col">
                <strong class="text-secondary">Kepada (Supplier):</strong>
                <address class="mt-2">
                    <strong>{{ $purchaseReturn->supplier->nama_supplier ?? 'Umum / Internal' }}</strong><br>
                    {{ $purchaseReturn->supplier->alamat ?? '' }}<br>
                    Telp: {{ $purchaseReturn->supplier->no_telp ?? '-' }}
                </address>
            </div>
            <div class="col-sm-4 invoice-col">
                <div class="bg-light p-3 rounded border">
                    <b>No. Retur:</b> <span class="float-right font-weight-bold">{{ $purchaseReturn->nomor_retur }}</span><br>
                    <b>Ref. Receiving:</b> <span class="float-right">{{ $purchaseReturn->receiving->nomor_penerimaan }}</span><br>
                    <b>Tanggal:</b> <span class="float-right">{{ $purchaseReturn->tanggal_retur->format('d/m/Y') }}</span><br>
                    <b>Status:</b> <span class="float-right badge badge-success">SELESAI</span>
                </div>
            </div>
        </div>

        {{-- Tabel Detail Barang --}}
        <div class="row">
             <div class="col-12 table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="bg-danger text-white">
                        <tr>
                            <th style="width: 5%">No</th>
                            <th style="width: 15%">Kode Part</th>
                            <th style="width: 35%">Nama Barang</th>
                            <th style="width: 30%">Alasan Retur</th>
                            <th style="width: 15%" class="text-center">Qty Dikembalikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseReturn->details as $index => $detail)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $detail->barang->part_code }}</td>
                            <td class="font-weight-bold">{{ $detail->barang->part_name }}</td>
                            <td class="text-muted font-italic">{{ $detail->alasan ?? 'Tidak ada keterangan' }}</td>
                            <td class="text-center font-weight-bold" style="font-size: 1.1em;">
                                {{ $detail->qty_retur }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Catatan --}}
        @if($purchaseReturn->catatan)
        <div class="row mt-4">
            <div class="col-12">
                <p class="lead text-muted" style="font-size: 1rem;">Catatan:</p>
                <div class="alert alert-light border">
                    <i class="fas fa-sticky-note mr-1 text-warning"></i> {{ $purchaseReturn->catatan }}
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@stop