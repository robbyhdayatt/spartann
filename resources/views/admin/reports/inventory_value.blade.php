@extends('adminlte::page')

@section('title', 'Laporan Nilai Persediaan')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Laporan Nilai Persediaan</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                <div class="info-box-content">
                    {{-- Label Dinamis --}}
                    <span class="info-box-text">
                        Total Nilai Persediaan 
                        (Basis Harga: 
                        @can('report-show-selling-in') 
                            <strong>Selling In / Beli</strong> 
                        @else 
                            <strong>Selling Out / Modal Dealer</strong> 
                        @endcan
                        )
                    </span>
                    <span class="info-box-number"><h2>Rp {{ number_format($totalValue, 0, ',', '.') }}</h2></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rincian Nilai per Item</h3>
            <div class="card-tools">
                <a href="{{ route('admin.reports.inventory-value.export') }}" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="inventory-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Lokasi</th>
                        <th>Barang</th>
                        <th>Rak</th>
                        <th class="text-right">Stok</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Total Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryDetails as $item)
                    @php
                        // Logic View Harga (Mirroring Controller)
                        $harga = 0;
                        if(auth()->user()->can('report-show-selling-in')) {
                            $harga = $item->barang->selling_in;
                        } else {
                            $harga = $item->barang->selling_out;
                        }
                        $total = $item->quantity * $harga;
                    @endphp
                    <tr>
                        <td>{{ $item->lokasi->nama_lokasi ?? '-' }}</td>
                        <td>{{ $item->barang->part_name ?? '-' }} ({{ $item->barang->part_code ?? '-' }})</td>
                        <td>{{ $item->rak->kode_rak ?? '-' }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($harga, 0, ',', '.') }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($total, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">Tidak ada stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#inventory-table').DataTable({ "responsive": true });
    });
</script>
@stop