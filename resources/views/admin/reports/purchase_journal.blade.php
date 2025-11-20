@extends('adminlte::page')

@section('title', 'Laporan Jurnal Pembelian')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Laporan Jurnal Pembelian</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Tanggal</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.reports.purchase-journal') }}" method="GET">
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
                @if(!$purchaseDetails->isEmpty())
                    <a href="{{ route('admin.reports.purchase-journal.export', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> Export
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <table id="purchase-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Tgl Terima</th>
                        <th>No Penerimaan</th>
                        <th>No PO</th>
                        <th>Supplier/Sumber</th>
                        <th>Barang</th>
                        <th class="text-right">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseDetails as $detail)
                    <tr>
                        <td>{{ $detail->receiving->tanggal_terima->format('d-m-Y') }}</td>
                        <td>{{ $detail->receiving->nomor_penerimaan }}</td>
                        <td>{{ $detail->receiving->purchaseOrder->nomor_po ?? '-' }}</td>
                        <td>
                            {{ $detail->receiving->purchaseOrder->supplier->nama_supplier ??
                               ($detail->receiving->purchaseOrder->sumberLokasi->nama_lokasi . ' (Internal)' ?? '-') }}
                        </td>
                        <td>{{ $detail->barang->part_name ?? '-' }}</td>
                        <td class="text-right">{{ $detail->qty_terima }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#purchase-table').DataTable({ "responsive": true });
    });
</script>
@stop
