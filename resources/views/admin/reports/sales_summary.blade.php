@extends('adminlte::page')

@section('title', 'Laporan Penjualan')

{{-- Tambahkan CSS untuk DataTables (jika plugin tidak memuatnya) --}}
@section('adminlte_css')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
@stop

@section('content_header')
    <h1 class="m-0 text-dark">Laporan Penjualan</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- FORM FILTER (Sudah Benar) --}}
                    <form action="{{ route('admin.reports.sales-summary') }}" method="GET">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="start_date">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">Tanggal Selesai</label>
                                <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="dealer_id">Pilih Dealer (Lokasi)</label>
                                <select name="dealer_id" class="form-control select2" @if(count($dealerList) == 1) readonly @endif>
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
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                <a href="{{ route('admin.reports.sales-summary.export', request()->query()) }}" class="btn btn-success">
                                    <i class="fa fa-download"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Tanggal Jual</th>
                                    <th>No. Faktur</th>
                                    <th>Dealer (Lokasi)</th>
                                    <th>Konsumen</th>
                                    <th>Sales</th>
                                    <th>Kode Barang</th>
                                    <th>Nama Barang</th>
                                    <th>Qty</th>
                                    <th>Total Penjualan</th>
                                    <th>Total Modal (HPP)</th>
                                    <th>Total Keuntungan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{--
                                  MODIFIKASI PENTING #1:
                                  Kita hapus <tr>..</tr> dari dalam @empty
                                --}}
                                @forelse($reportData as $data)
                                    @php
                                        // Hitung modal & keuntungan per baris
                                        $modal_satuan = $data->barang->harga_modal ?? 0;
                                        $total_modal = $data->qty_jual * $modal_satuan;
                                        $total_keuntungan = $data->subtotal - $total_modal;
                                    @endphp
                                    <tr>
                                        <td>{{ $data->penjualan->tanggal_jual->format('d-m-Y') }}</td>
                                        <td>{{ $data->penjualan->nomor_faktur }}</td>
                                        <td>{{ $data->penjualan->lokasi->nama_lokasi ?? '-' }}</td>
                                        <td>{{ $data->penjualan->konsumen->nama_konsumen ?? '-' }}</td>
                                        <td>{{ $data->penjualan->sales->nama ?? '-' }}</td>
                                        <td>{{ $data->barang->part_code ?? 'N/A' }}</td>
                                        <td>{{ $data->barang->part_name ?? 'N/A' }}</td>
                                        <td>{{ $data->qty_jual }}</td>
                                        <td>Rp {{ number_format($data->subtotal, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($total_modal, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($total_keuntungan, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    {{-- BIARKAN KOSONG. DataTables akan mengisi ini. --}}
                                @endforelse
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                {{-- TFOOT Anda sudah benar menggunakan <th> --}}
                                <tr>
                                    <th colspan="7" class="text-right">GRAND TOTAL</th>
                                    <th>{{ number_format($grandTotalQty, 0, ',', '.') }}</th>
                                    <th>Rp {{ number_format($grandTotalPenjualan, 0, ',', '.') }}</th>
                                    <th>Rp {{ number_format($grandTotalModal, 0, ',', '.') }}</th>
                                    <th>Rp {{ number_format($grandTotalKeuntungan, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    {{-- Impor skrip DataTables & Select2 --}}
    <script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4'
            });

            // Initialize DataTables
            $('#reportTable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "paging": true,
                "info": true,
                "searching": true,
                "ordering": true,
                "order": [[ 0, "desc" ]],

                {{--
                  MODIFIKASI PENTING #2:
                  Tambahkan ini untuk menggantikan @empty row
                --}}
                "language": {
                    "emptyTable": "Tidak ada data untuk filter yang dipilih.",
                    "zeroRecords": "Tidak ada data yang cocok ditemukan"
                },

                "footerCallback": function ( row, data, start, end, display ) {
                    // Footer ini statis dari PHP, jadi biarkan saja.
                }
            });
        });
    </script>
@endpush
