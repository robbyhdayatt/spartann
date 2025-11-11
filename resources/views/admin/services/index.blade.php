@extends('adminlte::page')

@section('title', 'Manajemen Service')

@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugin', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Manajemen Service</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Pesan Sukses/Error --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="icon fas fa-check"></i>{{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="icon fas fa-ban"></i>{{ session('error') }}
            </div>
        @endif

        @can('manage-service')
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Import Data Service</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.services.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="file">Pilih File Excel untuk Diimpor</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file" name="file" required>
                                <label class="custom-file-label" for="file">Pilih file</label>
                            </div>
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-upload"></i> Import
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Hanya file .xls, .xlsx, atau .csv. Pastikan kolom `dealer` sesuai kode dealer.</small>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- FILTER --}}
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Filter Data</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.services.index') }}" method="GET" id="filter-form">
                    <div class="row">
                        @if($canFilterByDealer && $listDealer->isNotEmpty())
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="dealer_code">Pilih Dealer:</label>
                                    <select name="dealer_code" id="dealer_code" class="form-control select2">
                                        <option value="all" {{ !$selectedDealer || $selectedDealer == 'all' ? 'selected' : '' }}>-- Semua Dealer --</option>
                                        @foreach ($listDealer as $dealer)
                                            <option value="{{ $dealer->kode_lokasi}}" {{ $selectedDealer == $dealer->kode_lokasi? 'selected' : '' }}>
                                                {{ $dealer->kode_lokasi}} - {{ $dealer->nama_lokasi}}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Tanggal Mulai (Import):</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">Tanggal Selesai (Import):</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                            </div>
                        </div>

                        <div class="col-md-3 d-flex align-items-end mb-3">
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                            @if(request()->has('start_date') || request()->has('end_date') || ($canFilterByDealer && $selectedDealer && $selectedDealer !== 'all'))
                                <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i> Reset
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                @can('export-service-report')
                <div class="mt-2">
                    <button type="button" class="btn btn-success" id="export-excel-btn">
                        <i class="fas fa-file-excel"></i> Export Excel (sesuai filter)
                    </button>
                    <small class="text-muted ml-2">Pilih Tanggal Mulai dan Selesai terlebih dahulu untuk mengaktifkan tombol ini.</small>
                </div>
                @endcan
            </div>
        </div>

        {{-- TABEL --}}
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Daftar Transaksi Service</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="services-table" class="table table-bordered table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No.</th>
                                <th>No. Invoice</th>
                                <th>Dealer</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Service Order</th>
                                <th>Tgl. Import</th>
                                <th class="text-right">Total</th>
                                <th class="text-center" style="width: 10%;">Status Cetak</th>
                                <th class="text-center" style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                <tr class="{{ $service->printed_at ? 'row-printed' : '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $service->invoice_no }}</strong></td>
                                    <td><span class="badge badge-secondary">{{ $service->dealer_code }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($service->reg_date)->isoFormat('DD MMM YYYY') }}</td>
                                    <td>{{ $service->customer_name }}</td>
                                    <td><span class="badge badge-info">{{ $service->service_order }}</span></td>
                                    <td>{{ $service->created_at->isoFormat('DD MMM YYYY, HH:mm') }}</td>
                                    <td class="text-right"><strong>@rupiah($service->total_amount)</strong></td>
                                    <td class="text-center" data-order="{{ $service->printed_at ? 1 : 0 }}">
                                        @if($service->printed_at)
                                            <span class="badge badge-success" title="Pada: {{ $service->printed_at->format('d/m/Y H:i') }}">
                                                <i class="fas fa-check"></i> Sudah Cetak
                                            </span>
                                        @else
                                            <span class="badge badge-warning">
                                                <i class="fas fa-times"></i> Belum
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.services.show', $service->id) }}" class="btn btn-xs btn-info" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center">Tidak ada data service ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    $(function () {
        bsCustomFileInput.init();
        $('.select2').select2({ theme: 'bootstrap4' });

        var table = $('#services-table').DataTable({
            responsive: true,
            autoWidth: false,
            paging: true,
            searching: true,
            ordering: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
                search: "",
                searchPlaceholder: "Cari di halaman ini..."
            },
            dom: "<'row'<'col-sm-12 col-md-6'lB><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'copy', text: '<i class="fas fa-copy"></i> Salin', className: 'btn btn-sm btn-default' },
                { extend: 'csv', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn btn-sm btn-default' },
                { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-sm btn-default' },
                { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-sm btn-default' },
                { extend: 'print', text: '<i class="fas fa-print"></i> Cetak', className: 'btn btn-sm btn-default' },
                { extend: 'colvis', text: '<i class="fas fa-eye"></i> Kolom', className: 'btn btn-sm btn-default' }
            ],
            columnDefs: [
                { orderable: false, targets: [0, 9] },
                { searchable: false, targets: [0, 7, 8, 9] }
            ],
            order: [[ 8, "asc" ]]
        });

        function checkExportButtonState() {
            var startDateValue = $('#start_date').val();
            var endDateValue = $('#end_date').val();
            $('#export-excel-btn').prop('disabled', !startDateValue || !endDateValue);
        }

        checkExportButtonState();

        $('#start_date, #end_date').on('change', checkExportButtonState);

        $('#export-excel-btn').on('click', function() {
            var startDateValue = $('#start_date').val();
            var endDateValue = $('#end_date').val();
            var dealerCodeValue = $('#dealer_code').val() || 'all';

            if (!startDateValue || !endDateValue) {
                alert('Silakan pilih Tanggal Mulai dan Tanggal Selesai terlebih dahulu.');
                return;
            }

            var exportUrl = "{{ route('admin.services.export.excel') }}" +
                            "?start_date=" + startDateValue +
                            "&end_date=" + endDateValue +
                            "&dealer_code=" + dealerCodeValue;

            window.location.href = exportUrl;
        });
    });
</script>
@endpush

@push('css')
<style>
    .row-printed td { background-color: #f8f9fa !important; color: #6c757d; }
    .row-printed .badge, .row-printed a.btn { opacity: 0.7; }
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px); padding-top: 0.375rem; }
    .dataTables_wrapper .row:first-child {
        margin-bottom: 0.5rem; padding-top: 0.5rem; background-color: #f4f6f9;
        border-bottom: 1px solid #dee2e6; padding-bottom: 0.5rem;
    }
    .dataTables_wrapper .dt-buttons { text-align: left; margin-bottom: 0.5rem; }
    .dataTables_wrapper .dataTables_filter { text-align: right; margin-bottom: 0.5rem; }
    .dataTables_wrapper .dataTables_filter input {
        width: 250px; display: inline-block; margin-left: 0.5rem;
    }
    .dataTables_wrapper .row:last-child { padding-top: 1rem; border-top: 1px solid #dee2e6; }
    .dataTables_wrapper .dataTables_info { padding-top: 0.375rem; }
    .dataTables_wrapper .dataTables_paginate { text-align: right; }
</style>
@endpush
