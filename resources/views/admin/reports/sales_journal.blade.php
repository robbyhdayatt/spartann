@extends('adminlte::page')

@section('title', 'Laporan Jurnal Penjualan')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Laporan Jurnal Penjualan</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Tanggal</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.reports.sales-journal') }}" method="GET">
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

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Hasil Laporan</h3>
            <div class="card-tools">
                @if(!$salesDetails->isEmpty())
                    <a href="{{ route('admin.reports.sales-journal.export', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> Export
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <table id="sales-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Faktur</th>
                        <th>Konsumen</th>
                        <th>Barang</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Harga Jual</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesDetails as $detail)
                    <tr>
                        <td>{{ $detail->penjualan->tanggal_jual->format('d-m-Y') }}</td>
                        <td>{{ $detail->penjualan->nomor_faktur }}</td>
                        <td>{{ $detail->penjualan->konsumen->nama_konsumen ?? '-' }}</td>
                        <td>{{ $detail->barang->part_name ?? 'Item Terhapus' }}</td>
                        <td class="text-right">{{ $detail->qty_jual }}</td>
                        <td class="text-right">{{ number_format($detail->harga_jual, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#sales-table').DataTable({ "responsive": true });
    });
</script>
@stop
