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

                            {{-- TAMBAHAN: Filter Dealer (Lokasi) --}}
                            <div class="col-md-3">
                                <label for="dealer_id">Pilih Dealer (Lokasi)</label>
                                <select name="dealer_id" class="form-control" @if(count($dealerList) == 1) readonly @endif>
                                    {{-- Jika user BUKAN admin, $dealerList hanya berisi 1 lokasi,
                                         tapi jika admin, dia bisa memilih "Semua Dealer" --}}
                                    @if(count($dealerList) > 1)
                                        <option value="">-- Semua Dealer --</option>
                                    @endif

                                    @foreach($dealerList as $dealer)
                                        {{-- Gunakan $selectedLokasiId dari controller --}}
                                        <option value="{{ $dealer->id }}" {{ $selectedLokasiId == $dealer->id ? 'selected' : '' }}>
                                            {{ $dealer->nama_lokasi }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- MODIFIKASI: Menggabungkan tombol filter & export --}}
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                {{-- Pastikan export mengambil semua parameter filter --}}
                                <a href="{{ route('admin.reports.sales-summary.export', request()->query()) }}" class="btn btn-success">
                                    <i class="fa fa-download"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <div class="table-responsive">
                        {{-- MODIFIKASI: Header, Body, dan Footer Tabel --}}
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead class="thead-dark">
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
                                    <tr>
                                        {{-- Sesuaikan colspan dengan jumlah kolom baru (11) --}}
                                        <td colspan="11" class="text-center">Tidak ada data untuk filter yang dipilih.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    {{-- Sesuaikan colspan (7 kolom pertama adalah teks) --}}
                                    <td colspan="7" class="text-right">GRAND TOTAL</td>
                                    <td>{{ number_format($grandTotalQty, 0, ',', '.') }}</td>
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
                // Urutkan berdasarkan kolom pertama (Tanggal Jual) secara descending (terbaru dulu)
                "order": [[ 0, "desc" ]],
                "footerCallback": function ( row, data, start, end, display ) {
                    // Biarkan footer statis
                }
            });
        });
    </script>
@endpush
