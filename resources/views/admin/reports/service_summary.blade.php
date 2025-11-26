@extends('adminlte::page')

@section('title', 'Laporan Service Summary')

@section('content_header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Laporan Service Summary (Parts Only)</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    {{-- Pastikan route 'admin.home' ada, jika error ganti 'admin.dashboard' sesuai route Anda --}}
                    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan Service Summary</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        {{-- Card Filter --}}
        <div class="card card-default">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Laporan</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.reports.service-summary') }}" method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Tanggal Mulai (Reg Date)</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Tanggal Akhir (Reg Date)</label>
                                <input type="date" name="end_date" class="form-control"
                                       value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>No Invoice (Opsional)</label>
                                <input type="text" name="invoice_no" class="form-control"
                                       placeholder="Cari Invoice..." value="{{ $invoiceNo }}">
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-group w-100">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search mr-1"></i> Tampilkan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Card Hasil Laporan --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">
                    <i class="fas fa-file-alt mr-1"></i>
                    Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong>
                    s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>
                </h3>
                <div class="card-tools ml-auto">
                    {{-- Tombol Export Excel --}}
                    <a href="{{ route('admin.reports.service-summary.export', request()->all()) }}"
                       class="btn btn-success btn-sm">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </a>
                </div>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-bordered text-nowrap table-striped">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th style="width: 5%">No.</th>
                            <th>Kode Part</th>
                            <th>Nama Part</th>
                            <th>Kategori</th>
                            <th>Qty Terjual</th>
                            <th>Total Penjualan (Gross)</th>
                            <th>Total Modal (HPP)</th>
                            <th>Keuntungan (Profit)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportData as $index => $row)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>
                                    <span class="badge badge-light">{{ $row->item_code }}</span>
                                </td>
                                <td>{{ $row->item_name }}</td>
                                <td class="text-center">
                                    @if(str_contains(strtoupper($row->item_category), 'OLI'))
                                        <span class="badge badge-info">OLI</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $row->item_category }}</span>
                                    @endif
                                </td>
                                <td class="text-center font-weight-bold">{{ number_format($row->total_qty, 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($row->total_penjualan, 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($row->total_modal, 0, ',', '.') }}</td>
                                <td class="text-right {{ $row->total_keuntungan >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                    Rp {{ number_format($row->total_keuntungan, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="my-3">
                                        <i class="fas fa-box-open fa-3x text-gray-300"></i>
                                        <p class="mt-2">Tidak ada data part terjual pada periode ini.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light">
                        <tr class="font-weight-bold">
                            <td colspan="4" class="text-right text-uppercase">Grand Total</td>
                            <td class="text-center">{{ number_format($grandTotalQty, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($grandTotalPenjualan, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($grandTotalModal, 0, ',', '.') }}</td>
                            <td class="text-right text-success">Rp {{ number_format($grandTotalKeuntungan, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@stop

@section('css')
    {{-- Tambahkan CSS custom jika perlu --}}
    <style>
        .table th, .table td { vertical-align: middle; }
    </style>
@stop

@section('js')
    <script>
        console.log('Service Summary Report Loaded');
    </script>
@stop