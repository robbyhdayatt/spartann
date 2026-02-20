@extends('adminlte::page')

@section('title', 'Laporan Ringkasan Penjualan')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Laporan Ringkasan Penjualan</h1>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-body">
            {{-- FORM FILTER --}}
            <form action="{{ route('admin.reports.sales-summary') }}" method="GET">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Dealer (Lokasi)</label>
                            <select name="dealer_id" class="form-control select2" @if(count($dealerList) <= 1) readonly @endif>
                                @if(count($dealerList) > 1)
                                    <option value="">-- Semua Dealer --</option>
                                @endif
                                @foreach($dealerList as $dealer)
                                    <option value="{{ $dealer->id }}" {{ $selectedLokasiId == $dealer->id ? 'selected' : '' }}>
                                        {{ $dealer->nama_lokasi }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                                <i class="fas fa-search mr-1"></i> Terapkan Filter
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <hr>

            {{-- HEADER TABEL & TOMBOL EXPORT --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="m-0 text-secondary"><i class="fas fa-list mr-2"></i>Hasil Laporan Penjualan</h5>
                
                {{-- Tombol Export Dipindah ke Sini --}}
                <a href="{{ route('admin.reports.sales-summary.export', request()->query()) }}" class="btn btn-success shadow-sm">
                    <i class="fas fa-file-excel mr-1"></i> Export ke Excel
                </a>
            </div>

            {{-- TABEL DATA --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="reportTable">
                    <thead class="bg-gradient-dark">
                        <tr>
                            <th>Tanggal</th>
                            <th>Faktur</th>
                            <th>Dealer</th>
                            <th>Barang</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Total Jual</th>
                            <th class="text-right">Total HPP</th>
                            <th class="text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData as $data)
                            @php
                                $hpp = $data->barang->selling_out ?? 0;
                                $total_modal = $data->qty_jual * $hpp;
                                $profit = $data->subtotal - $total_modal;
                            @endphp
                            <tr>
                                <td class="align-middle">{{ $data->penjualan->tanggal_jual->format('d-m-Y') }}</td>
                                <td class="align-middle font-weight-bold">{{ $data->penjualan->nomor_faktur }}</td>
                                <td class="align-middle">{{ $data->penjualan->lokasi->nama_lokasi ?? '-' }}</td>
                                <td class="align-middle">{{ $data->barang->part_name ?? '-' }}</td>
                                <td class="align-middle text-center font-weight-bold">{{ $data->qty_jual }}</td>
                                <td class="align-middle text-right">Rp {{ number_format($data->subtotal, 0, ',', '.') }}</td>
                                <td class="align-middle text-right text-muted">Rp {{ number_format($total_modal, 0, ',', '.') }}</td>
                                <td class="align-middle text-right font-weight-bold {{ $profit > 0 ? 'text-success' : ($profit < 0 ? 'text-danger' : '') }}">
                                    Rp {{ number_format($profit, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <th colspan="4" class="text-right text-uppercase">Grand Total Keseluruhan</th>
                            <th class="text-center text-primary" style="font-size: 1.1rem;">{{ number_format($grandTotalQty, 0, ',', '.') }}</th>
                            <th class="text-right text-primary" style="font-size: 1.1rem;">Rp {{ number_format($grandTotalPenjualan, 0, ',', '.') }}</th>
                            <th class="text-right text-secondary" style="font-size: 1.1rem;">Rp {{ number_format($grandTotalModal, 0, ',', '.') }}</th>
                            <th class="text-right text-success" style="font-size: 1.1rem;">Rp {{ number_format($grandTotalKeuntungan, 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@stop

@push('css')
<style>
    /* CSS untuk merapikan ukuran Select2 agar sama dengan input form Bootstrap 4 */
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        padding-left: 0.75rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4'
        });
        
        $('#reportTable').DataTable({
            "responsive": true,
            "order": [[ 0, "desc" ]], // Urutkan dari tanggal terbaru
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json"
            }
        });
    });
</script>
@stop