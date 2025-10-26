@extends('adminlte::page')

@section('title', 'Manajemen Service')

{{-- Aktifkan plugin DataTables dan plugin tambahannya (untuk Buttons) --}}
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

        {{-- Box Impor (Collapsed) --}}
        @can('manage-service')
        <div class="card collapsed-card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Import Data Service</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" style="display: none;">
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

    {{-- Box Filter --}}
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">Filter Data</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            {{-- Form untuk Filter Tampilan --}}
            <form action="{{ route('admin.services.index') }}" method="GET" id="filter-form">
                <div class="row">              
                    {{-- Filter Dealer (Hanya untuk Superadmin/PIC) --}}
                    @if($canFilterByDealer && $listDealer->isNotEmpty())
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="dealer_code">Pilih Dealer:</label>
                                <select name="dealer_code" id="dealer_code" class="form-control select2">
                                    <option value="all" {{ !$selectedDealer || $selectedDealer == 'all' ? 'selected' : '' }}>-- Semua Dealer --</option>
                                    @foreach ($listDealer as $dealer)
                                        <option value="{{ $dealer->kode_gudang }}" {{ $selectedDealer == $dealer->kode_gudang ? 'selected' : '' }}>
                                            {{ $dealer->kode_gudang }} - {{ $dealer->nama_gudang }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                    {{-- Filter Tanggal Import (created_at) --}}
                    <div class="col-md-{{ $canFilterByDealer ? '3' : '4' }}">
                        <div class="form-group">
                            <label for="filter_date">Tanggal Import:</label>
                            <input type="date" name="filter_date" id="filter_date" class="form-control" value="{{ $filterDate ?? '' }}">
                        </div>
                    </div>

                    {{-- Tombol Filter dan Reset --}}
                    <div class="col-md-5 d-flex align-items-end mb-3">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        @if(($canFilterByDealer && $selectedDealer && $selectedDealer !== 'all') || $filterDate)
                            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset Filter
                            </a>
                        @endif
                    </div>
                </div>
            </form>
            {{-- Tombol Export Excel Harian --}}
            @can('export-service-report')
            <div class="mt-2">
                <button type="button" class="btn btn-success" id="export-excel-btn">
                    <i class="fas fa-file-excel"></i> Export Excel Laporan Harian (sesuai filter)
                </button>
                <small class="text-muted ml-2">Pilih Tanggal Import terlebih dahulu untuk mengaktifkan tombol ini.</small>
            </div>
            @endcan
        </div>
    </div>
    {{-- Box Tabel Daftar Service --}}
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title">
                Daftar Transaksi Service
                @if($canFilterByDealer && $selectedDealer && $selectedDealer !== 'all')
                    (Dealer: {{ $selectedDealer }} - {{ $listDealer->firstWhere('kode_gudang', $selectedDealer)->nama_gudang ?? '' }})
                @elseif ($canFilterByDealer && (!$selectedDealer || $selectedDealer == 'all'))
                    (Semua Dealer)
                @elseif (!$canFilterByDealer && $selectedDealer)
                    (Dealer: {{ Auth::user()->lokasi->nama_gudang ?? $selectedDealer }})
                @endif
            </h3>
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
                                <th>Plat No.</th>
                                <th class="text-right">Total</th>
                                <th class="text-center" style="width: 10%;">Status Cetak</th>
                                <th class="text-center" style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                <tr class="{{ $service->printed_at ? 'row-printed' : '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ $service->invoice_no }}</strong>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $service->dealer_code }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($service->reg_date)->isoFormat('DD MMM YYYY') }}</td>
                                    <td>{{ $service->customer_name }}</td>
                                    <td><span class="badge badge-dark">{{ $service->plate_no }}</span></td>
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
                                    <td colspan="9" class="text-center">Tidak ada data service ditemukan.</td>
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

        $('.select2').select2({
              theme: 'bootstrap4'
        });

        var table = $('#services-table').DataTable({
            // ... (Opsi DataTables Anda) ...
             "responsive": true,
            "autoWidth": false,
            "paging": true,
            "lengthChange": true,
            "info": true,
            "searching": true,
            "ordering": true,
            "language": {
                 "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
                 "search": "",
                 "searchPlaceholder": "Cari di halaman ini..."
             },
            "dom": "<'row'<'col-sm-12 col-md-6'lB><'col-sm-12 col-md-6'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "buttons": [
                 { extend: 'copy', text: '<i class="fas fa-copy"></i> Salin', className: 'btn btn-sm btn-default' },
                 { extend: 'csv', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn btn-sm btn-default' },
                 { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel (Halaman)', className: 'btn btn-sm btn-default' }, // Tombol excel bawaan datatables
                 { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-sm btn-default' },
                 { extend: 'print', text: '<i class="fas fa-print"></i> Cetak', className: 'btn btn-sm btn-default' },
                 { extend: 'colvis', text: '<i class="fas fa-eye"></i> Kolom', className: 'btn btn-sm btn-default' }
            ],
             "columnDefs": [
                 { "orderable": false, "targets": [0, 8] },
                 { "searchable": false, "targets": [0, 7, 8] }
             ],
            "order": [[ 7, "asc" ]] // Urutkan berdasarkan status cetak
        });

        // ++ LOGIKA UNTUK TOMBOL EXPORT EXCEL HARIAN ++
        function checkExportButtonState() {
            var filterDateValue = $('#filter_date').val();
            if (filterDateValue) {
                $('#export-excel-btn').prop('disabled', false);
            } else {
                $('#export-excel-btn').prop('disabled', true);
            }
        }

        // Cek saat halaman dimuat
        checkExportButtonState();

        // Cek saat tanggal diubah
        $('#filter_date').on('change', function() {
            checkExportButtonState();
        });

        // Handle klik tombol export
        $('#export-excel-btn').on('click', function() {
            var filterDateValue = $('#filter_date').val();
            var dealerCodeValue = $('#dealer_code').val() || 'all'; // Ambil dealer code, default 'all'

            if (!filterDateValue) {
                alert('Silakan pilih Tanggal Import terlebih dahulu.');
                return;
            }

            // Buat URL export dengan parameter filter
            var exportUrl = "{{ route('admin.services.export.excel') }}";
            exportUrl += "?filter_date=" + filterDateValue;
            exportUrl += "&dealer_code=" + dealerCodeValue;

            // Redirect ke URL export
            window.location.href = exportUrl;
        });
        // ++ END LOGIKA EXPORT ++

    });
</script>
@endpush

@push('css')
<style>
/* ... (CSS Anda sebelumnya tetap sama) ... */
 /* Style untuk baris yang sudah dicetak */
    .row-printed td {
        background-color: #f8f9fa !important;
        color: #6c757d;
    }
    .row-printed .badge {
        opacity: 0.7;
    }
    .row-printed a.btn {
        opacity: 0.7;
    }

    /* Samakan tinggi select2 */
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px);
        padding-top: 0.375rem;
    }

    /* Atur layout DataTables DOM (Buttons dan Search) */
    .dataTables_wrapper .row:first-child {
        margin-bottom: 0.5rem;
        padding-top: 0.5rem;
        background-color: #f4f6f9; /* Beri sedikit latar */
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.5rem;
    }
    .dataTables_wrapper .dt-buttons {
        text-align: left;
        margin-bottom: 0.5rem; /* Beri jarak jika di mobile */
    }
    .dataTables_wrapper .dataTables_filter {
        text-align: right;
        margin-bottom: 0.5rem; /* Beri jarak jika di mobile */
    }
    .dataTables_wrapper .dataTables_filter input {
        width: 250px;
        display: inline-block;
        margin-left: 0.5rem;
    }
    /* Atur layout Paginasi dan Info di bawah */
    .dataTables_wrapper .row:last-child {
         padding-top: 1rem;
         border-top: 1px solid #dee2e6;
    }
    .dataTables_wrapper .dataTables_info {
        padding-top: 0.375rem; /* Agar sejajar tombol paginasi */
    }
    .dataTables_wrapper .dataTables_paginate {
        text-align: right;
    }
</style>
@endpush