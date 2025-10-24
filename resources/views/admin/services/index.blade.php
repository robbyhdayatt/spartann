@extends('adminlte::page')

@section('title', 'Manajemen Service')

@section('plugins.Datatables', true)
@section('plugins.Select2', true) {{-- Aktifkan Select2 --}}

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
        <div class="card collapsed-card">
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

        {{-- ++ Box Filter Dealer (Hanya untuk Superadmin/PIC) ++ --}}
        @if($isSuperAdminOrPic && $dealers->isNotEmpty())
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
                <form action="{{ route('admin.services.index') }}" method="GET" class="form-inline">
                    <div class="form-group mb-2 mr-sm-2">
                        <label for="dealer_code" class="mr-sm-2">Pilih Dealer:</label>
                        {{-- Gunakan Select2 untuk dropdown --}}
                        <select name="dealer_code" id="dealer_code" class="form-control select2" style="width: 250px;"> {{-- Beri style width --}}
                            <option value="all" {{ !$selectedDealer || $selectedDealer == 'all' ? 'selected' : '' }}>-- Semua Dealer --</option>
                            @foreach ($dealers as $dealer)
                                <option value="{{ $dealer->kode_dealer }}" {{ $selectedDealer == $dealer->kode_dealer ? 'selected' : '' }}>
                                    {{ $dealer->kode_dealer }} - {{ $dealer->nama_dealer }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                    {{-- Tombol Reset hanya muncul jika filter aktif --}}
                    @if(request('dealer_code') && request('dealer_code') !== 'all')
                         <a href="{{ route('admin.services.index') }}" class="btn btn-secondary mb-2 ml-2">
                             <i class="fas fa-sync-alt"></i> Reset
                         </a>
                     @endif
                </form>
            </div>
        </div>
        @endif

        {{-- Box Tabel Daftar Service --}}
        <div class="card">
            <div class="card-header">
                 {{-- Tampilkan dealer yang difilter di judul --}}
                <h3 class="card-title">
                    Daftar Transaksi Service
                    @if($isSuperAdminOrPic && $selectedDealer && $selectedDealer !== 'all')
                        (Dealer: {{ $selectedDealer }} - {{ $dealers->firstWhere('kode_dealer', $selectedDealer)->nama_dealer ?? '' }})
                    @elseif ($isSuperAdminOrPic && (!$selectedDealer || $selectedDealer == 'all'))
                        (Semua Dealer)
                    @endif
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive"> {{-- Bungkus tabel agar responsif --}}
                    <table id="services-table" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No.</th>
                                <th>No. Invoice</th>
                                <th>Dealer</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Plat No.</th>
                                <th class="text-right">Total</th>
                                <th class="text-center" style="width: 10%;">Cetak</th>
                                <th class="text-center" style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                <tr>
                                    <td>{{ ($services->currentPage() - 1) * $services->perPage() + $loop->iteration }}</td>
                                    <td>{{ $service->invoice_no }}</td>
                                    <td><span class="badge badge-secondary">{{ $service->dealer_code }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($service->reg_date)->isoFormat('DD MMM YYYY') }}</td> {{-- Format tanggal lebih pendek --}}
                                    <td>{{ $service->customer_name }}</td>
                                    <td>{{ $service->plate_no }}</td>
                                    <td class="text-right">@rupiah($service->total_amount)</td>
                                    <td class="text-center">
                                        @if($service->printed_at)
                                            <span class="badge badge-success" title="Pada: {{ $service->printed_at->format('d/m/Y H:i') }}">
                                                <i class="fas fa-check"></i> Sudah Cetak
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times"></i> Belum
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.services.show', $service->id) }}" class="btn btn-xs btn-info" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                         {{-- Tombol Edit sudah dihapus --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- Sesuaikan colspan dengan jumlah kolom --}}
                                    <td colspan="9" class="text-center">Tidak ada data service ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div> {{-- End table-responsive --}}

                {{-- Link Paginasi Laravel --}}
                <div class="mt-3 d-flex justify-content-center"> {{-- Pusatkan paginasi --}}
                    {{-- ++ PERBAIKAN: Tentukan view pagination Bootstrap 4 ++ --}}
                    {{ $services->appends(request()->query())->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    $(function () {
        // Inisialisasi bs-custom-file-input
        bsCustomFileInput.init();

        // Inisialisasi Select2
        $('.select2').select2({
             theme: 'bootstrap4'
        });

        // Inisialisasi DataTables (tanpa fitur bawaan, hanya untuk tampilan)
        $('#services-table').DataTable({
            "responsive": true,
            "lengthChange": false, // Paging & length diatur Laravel
            "autoWidth": false,
            "paging": false,       // Paging diatur Laravel
            "info": false,         // Info diatur Laravel (atau bisa diaktifkan jika perlu)
            "searching": true,     // Aktifkan search DataTables
            "ordering": true,      // Aktifkan sorting DataTables
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json",
                 "search": "_INPUT_",
                 "searchPlaceholder": "Cari data...",
            },
            "columnDefs": [
                { "orderable": false, "targets": [0, 8] }, // No & Aksi tidak bisa diurutkan
                { "searchable": false, "targets": [0, 8] } // No & Aksi tidak bisa dicari
            ],
            // Hapus order default agar menggunakan 'latest' dari Controller
             "order": []
            // Atau jika ingin sort default berdasarkan kolom lain (misal Tanggal index 3 descending):
            // "order": [[ 3, "desc" ]]
        });
    });
</script>
@endpush

@push('css')
{{-- Style tambahan jika perlu --}}
<style>
    /* Atur lebar search box DataTables */
    .dataTables_filter {
        width: 100%;
        text-align: right; /* Pindahkan ke kanan */
    }
    .dataTables_filter input {
        width: 250px; /* Atur lebar input */
        display: inline-block;
        margin-left: 10px;
    }
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px); /* Samakan tinggi select2 dengan input lain */
    }
</style>
@endpush

