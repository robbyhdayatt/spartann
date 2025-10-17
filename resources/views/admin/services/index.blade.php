@extends('adminlte::page')

@section('title', 'Manajemen Service')

@section('plugins.Datatables', true)

@section('content_header')
    <h1>Manajemen Service</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
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

        {{-- Fitur Impor tetap ada --}}
        @can('manage-service')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Import Data Service</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                {{-- ++ PERUBAHAN 1: Kolom petunjuk dihapus, form impor dibuat full width ++ --}}
                <div class="row">
                    <div class="col-md-12">
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
                                <small class="form-text text-muted">Hanya file .xls, .xlsx, atau .csv yang diizinkan. Pastikan kolom `dealer` sesuai dengan kode dealer Anda.</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endcan

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Transaksi Service</h3>
            </div>
            <div class="card-body">
                <table id="services-table" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No.</th>
                            <th>No. Invoice</th>
                            <th>Dealer</th>
                            <th>Tanggal Registrasi</th>
                            <th>Pelanggan</th>
                            <th>Plat Nomor</th>
                            <th class="text-right">Total Tagihan</th>
                            <th class="text-center" style="width: 10%;">Status Cetak</th>
                            <th class="text-center" style="width: 10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $service->invoice_no }}</td>
                                <td><span class="badge badge-secondary">{{ $service->dealer_code }}</span></td>
                                <td>{{ \Carbon\Carbon::parse($service->reg_date)->isoFormat('D MMMM YYYY') }}</td>
                                <td>{{ $service->customer_name }}</td>
                                <td>{{ $service->plate_no }}</td>
                                <td class="text-right">@rupiah($service->total_amount)</td>
                                <td class="text-center">
                                    @if($service->printed_at)
                                        <span class="badge badge-success" title="Dicetak pada: {{ $service->printed_at->format('d/m/Y H:i') }}">
                                            <i class="fas fa-check"></i> Sudah Cetak
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times"></i> Belum Cetak
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.services.show', $service->id) }}" class="btn btn-xs btn-info" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @can('manage-service')
                                        <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-xs btn-warning" title="Edit Service">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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

        // Inisialisasi DataTables
        $('#services-table').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "paging": true,
            "info": true,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
            },

            {{-- ++ PERUBAHAN 2: Mengaktifkan sorting di kolom Status Cetak ++ --}}
            "columnDefs": [
                {
                    "orderable": false,
                    "searchable": false,
                    "targets": [8] // Hanya nonaktifkan sort/search di kolom 'Aksi' (index 8)
                }
            ],

            {{-- ++ PERUBAHAN 3: Sorting default berdasarkan Status Cetak (index 7) ++ --}}
            "order": [
                [ 7, "asc" ] // "asc" akan mengurutkan 'Belum Cetak' (B) sebelum 'Sudah Cetak' (S)
            ]
        });
    });
</script>
@endpush
