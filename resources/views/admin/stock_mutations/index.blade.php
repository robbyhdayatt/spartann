@extends('adminlte::page')

@section('title', 'Mutasi Stok')
@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugin', true)

@section('content_header')
    <h1><i class="fas fa-exchange-alt text-primary mr-2"></i> Mutasi Stok</h1>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible shadow-sm">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <i class="icon fas fa-check"></i> {{ session('success') }}
    </div>
@endif

{{-- FILTER TANGGAL --}}
<div class="card card-outline card-primary shadow-sm">
    <div class="card-header">
        <h3 class="card-title">Filter Data</h3>
    </div>
    <div class="card-body">
        <form id="filter-form">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="start_date">Tanggal Mulai:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label for="end_date">Tanggal Selesai:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                    </div>
                </div>

                <div class="col-md-4 d-flex align-items-end mb-3">
                    <button type="submit" class="btn btn-primary mr-2 shadow-sm">
                        <i class="fas fa-filter mr-1"></i> Terapkan
                    </button>
                    <button type="button" id="btn-reset" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- TABEL MUTASI --}}
<div class="card card-outline card-info shadow-sm">
    <div class="card-header">
        <h3 class="card-title mt-1">Daftar Mutasi Antar Gudang/Dealer</h3>
        <div class="card-tools">
            @can('create-stock-transaction')
                <a href="{{ route('admin.stock-mutations.create') }}" class="btn btn-primary btn-sm shadow-sm"><i class="fas fa-plus"></i> Buat Mutasi Baru</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="mutation-table" class="table table-bordered table-striped table-hover" width="100%">
                <thead class="bg-light">
                    <tr>
                        <th width="5%">No.</th>
                        <th>Nomor Mutasi</th>
                        <th>Barang / Part</th>
                        <th class="text-center">Qty</th>
                        <th>Asal & Tujuan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    $(function () {
        var table = $('#mutation-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: "{{ route('admin.stock-mutations.index') }}",
                data: function (d) {
                    // Inject parameter filter ke request AJAX
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                }
            },
            order: [[1, "desc"]], // Mengurutkan nomor mutasi yang terbaru
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'nomor_mutasi_html', name: 'nomor_mutasi' },
                { data: 'part_html', name: 'barang.part_name' },
                { data: 'jumlah', name: 'jumlah', className: 'text-center font-weight-bold text-primary' },
                { data: 'rute_html', name: 'lokasiAsal.nama_lokasi', orderable: false, searchable: false },
                { data: 'status', name: 'status', className: 'text-center' },
                { data: 'aksi', name: 'aksi', orderable: false, searchable: false, className: 'text-center' }
            ],
            language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json" }
        });

        // Trigger filter ketika tombol Terapkan diklik
        $('#filter-form').on('submit', function(e) {
            e.preventDefault();
            table.ajax.reload();
        });

        // Trigger reset (kembali ke rentang 1 bulan terakhir)
        $('#btn-reset').on('click', function() {
            let today = new Date();
            let firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            // Format YYYY-MM-DD
            let todayStr = today.toISOString().split('T')[0];
            let firstDayStr = firstDay.toISOString().split('T')[0];

            $('#start_date').val(firstDayStr);
            $('#end_date').val(todayStr);
            
            table.ajax.reload();
        });
    });
</script>
@endpush