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
        {{-- Pesan Sukses/Error Bawaan --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="icon fas fa-check"></i>{{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <i class="icon fas fa-ban"></i>{{ session('error') }}
            </div>
        @endif

        {{-- [MODIFIKASI] KOTAK KHUSUS MENAMPILKAN DETAIL ERROR IMPORT --}}
        @if (session('import_errors'))
            <div class="alert alert-warning alert-dismissible shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan! Ada data yang gagal diproses:</h5>
                <p class="mb-2">Beberapa baris dalam file Excel Anda tidak dapat diimpor karena alasan berikut:</p>
                <ul style="max-height: 200px; overflow-y: auto; background: rgba(255,255,255,0.5); padding: 10px 10px 10px 30px; border-radius: 5px; margin-bottom: 0;">
                    @foreach (session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- IMPORT SECTION: HANYA PC DEALER (manage-service) --}}
        @can('manage-service')
        <div class="card card-outline card-secondary shadow-sm">
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
                    </div>
                    
                    {{-- Input Tanggal Laporan --}}
                    <div class="form-group mt-3">
                        <label for="tanggal_laporan">Tanggal Laporan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal_laporan" name="tanggal_laporan" value="{{ date('Y-m-d') }}" required>
                        <small class="form-text text-muted">Tanggal laporan ini akan menjadi penentu waktu transaksi (Otomatis hari ini).</small>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- FILTER --}}
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Filter Data</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.services.index') }}" method="GET" id="filter-form">
                    <div class="row">
                        {{-- Dropdown Dealer Hanya Muncul Jika User Punya Akses (SA/PIC/Pusat) --}}
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
                                <label for="start_date">Tanggal Mulai:</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">Tanggal Selesai</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                            </div>
                        </div>

                        <div class="col-md-3 d-flex align-items-end mb-3">
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>

                {{-- Tombol Export Excel (Sesuai Hak Akses) --}}
                @can('view-service')
                <div class="mt-2">
                    <button type="button" class="btn btn-success shadow-sm" id="export-excel-btn">
                        <i class="fas fa-file-excel"></i> Export Excel (sesuai filter)
                    </button>
                    <small class="text-muted ml-2">Pilih Tanggal Mulai dan Selesai terlebih dahulu untuk mengaktifkan tombol ini.</small>
                </div>
                @endcan
            </div>
        </div>

        {{-- TABEL SERVER-SIDE PROCESSING --}}
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Daftar Transaksi Service</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="services-table" class="table table-bordered table-striped table-hover" style="width:100%">
                        <thead class="bg-light">
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
                                <th class="text-center" style="width: 8%;">Aksi</th>
                            </tr>
                        </thead>
                        {{-- TBODY dikosongkan karena DataTables AJAX yang akan mengisinya otomatis --}}
                        <tbody></tbody>
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
            processing: true,
            serverSide: true, // Mengaktifkan Server-Side Processing
            responsive: true,
            autoWidth: false,
            ajax: {
                url: "{{ route('admin.services.index') }}",
                data: function (d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                    d.dealer_code = $('#dealer_code').val();
                }
            },
            pageLength: 25, 
            lengthMenu: [[10, 25, 50, 100, 500, 1000], [10, 25, 50, 100, 500, 1000]],
            searching: true,
            ordering: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
                search: "",
                searchPlaceholder: "Cari nomor invoice/pelanggan...",
                lengthMenu: "Tampilkan _MENU_ data" 
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
            // Pemetaan Kolom dari Response JSON Yajra DataTables
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'invoice_no', name: 'invoice_no', render: function(data) { return '<strong>'+data+'</strong>'; } },
                { data: 'dealer', name: 'dealer', searchable: false, orderable: false, render: function(data) { return '<span class="badge badge-secondary">'+data+'</span>'; } },
                { data: 'reg_date', name: 'reg_date' },
                { data: 'customer_name', name: 'customer_name' },
                { data: 'service_order', name: 'service_order', render: function(data) { return '<span class="badge badge-info">'+data+'</span>'; } },
                { data: 'created_at', name: 'created_at', render: function(data) {
                    if(!data) return '-'; 
                    let d = new Date(data); 
                    return d.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) + ', ' + d.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
                }},
                { data: 'total_amount', name: 'total_amount', className: 'text-right', render: function(data) { return '<strong>'+data+'</strong>'; } },
                { data: 'printed_at', name: 'printed_at', className: 'text-center', render: function(data) {
                    if(data) {
                        let d = new Date(data);
                        let dStr = d.toLocaleDateString('id-ID') + ' ' + d.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
                        return '<span class="badge badge-success" title="Pada: '+dStr+'"><i class="fas fa-check"></i> Sudah Cetak</span>';
                    } else {
                        return '<span class="badge badge-warning"><i class="fas fa-times"></i> Belum</span>';
                    }
                }},
                { data: 'aksi', name: 'aksi', orderable: false, searchable: false, className: 'text-center' }
            ],
            // [MODIFIKASI] Target Default Sorting dialihkan ke Kolom 8 (Status Cetak)
            order: [[ 8, "asc" ]], 
            createdRow: function(row, data, dataIndex) {
                if (data.printed_at) {
                    $(row).addClass('row-printed');
                }
            }
        });

        // AJAX FILTER SUBMIT (Tanpa Refresh Halaman)
        $('#filter-form').on('submit', function(e) {
            e.preventDefault();
            table.ajax.reload(); 
            checkExportButtonState();
            
            const url = new URL(window.location);
            url.searchParams.set('start_date', $('#start_date').val());
            url.searchParams.set('end_date', $('#end_date').val());
            if ($('#dealer_code').length && $('#dealer_code').val()) {
                url.searchParams.set('dealer_code', $('#dealer_code').val());
            }
            window.history.pushState({}, '', url);
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
            var dealerCodeValue = $('#dealer_code').length ? $('#dealer_code').val() : 'all';

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