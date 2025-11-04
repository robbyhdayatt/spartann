@extends('adminlte::page')

@section('title', 'Laporan Penjualan')

@section('content_header')
    <h1 class="m-0 text-dark">Laporan Penjualan</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    
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
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                            <div class="col-md-3 d-flex align-items-end justify-content-end">
                                <a href="{{ route('admin.reports.sales-summary.export', request()->query()) }}" class="btn btn-success">
                                    <i class="fa fa-download"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Kode Barang</th>
                                    <th>Nama Barang</th>
                                    <th>Qty Terjual</th>
                                    <th>Total Penjualan</th>
                                    <th>Total Modal (HPP)</th>
                                    <th>Total Keuntungan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData as $data)
                                    <tr>
                                        <td>{{ $data->part_code }}</td>
                                        <td>{{ $data->part_name }}</td>
                                        <td>{{ $data->total_qty }}</td>
                                        <td>Rp {{ number_format($data->total_penjualan, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($data->total_modal, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($data->total_keuntungan, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    <td colspan="2" class="text-right">GRAND TOTAL</td>
                                    <td>{{ $grandTotalQty }}</td>
                                    <td>Rp {{ number_format($grandTotalPenjualan, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($grandTotalModal, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($grandTotalKeuntungan, 0, ',', '.') }}</td>
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
    <script>
        // Inisialisasi DataTables untuk sort dan search
        $(document).ready(function() {
            $('#reportTable').DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "paging": true,
                "info": true,
                "searching": true,
                "ordering": true,
                // Hilangkan footer dari perhitungan DataTables
                "footerCallback": function ( row, data, start, end, display ) {
                    // Biarkan footer statis
                }
            });
        });
    </script>
@endpush