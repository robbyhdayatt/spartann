@extends('adminlte::page')

@section('title', 'Analisis Penjualan & Pembelian')

@section('content_header')
    <h1>Analisis Penjualan & Pembelian</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Laporan</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.reports.sales-purchase-analysis') }}" method="GET">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                    </div>
                    <div class="col-md-5">
                         <div class="form-group">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                         <div class="form-group w-100">
                            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top 10 Barang Terlaris (Qty)</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Barang</th>
                                <th class="text-right">Total Terjual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topSellingParts as $part)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $part->barang->part_name ?? '-' }}</td>
                                <td class="text-right">{{ $part->total_qty }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top 10 Barang Sering Dibeli (Qty)</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                         <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Barang</th>
                                <th class="text-right">Total Diterima</th>
                            </tr>
                        </thead>
                        <tbody>
                             @forelse($topPurchasedParts as $part)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $part->barang->part_name ?? '-' }}</td>
                                <td class="text-right">{{ $part->total_qty }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
