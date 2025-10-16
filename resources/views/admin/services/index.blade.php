@extends('adminlte::page')

@section('title', 'Manajemen Service')

{{-- ++ MODIFIKASI 1: Tambahkan plugin DataTables ++ --}}
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

        {{-- ++ MODIFIKASI 2: Bungkus Card Impor dengan @can ++ --}}
        {{-- Card ini hanya akan muncul untuk user yang memiliki izin 'manage-service' --}}
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
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> Petunjuk Import File Excel</h5>
                            <p>Pastikan file Excel Anda memiliki kolom-kolom berikut dengan urutan yang sesuai:</p>
                            <ol>
                                <li><strong>invoice_no</strong></li>
                                <li><strong>reg_date</strong> (Format: YYYY-MM-DD)</li>
                                <li><strong>dealer</strong></li>
                                <li><strong>customer_name</strong></li>
                                <li><strong>plate_no</strong></li>
                                <li><strong>total_labor</strong></li>
                                <li><strong>total_part_service</strong></li>
                                <li><strong>total_oil_service</strong></li>
                                <li><strong>total_retail_parts</strong></li>
                                <li><strong>total_retail_oil</strong></li>
                                <li><strong>benefit_amount</strong></li>
                                <li><strong>total_amount</strong></li>
                                <li><strong>e_payment_amount</strong></li>
                                <li><strong>cash_amount</strong></li>
                                <li><strong>debit_amount</strong></li>
                                <li><strong>total_payment</strong></li>
                                <li><strong>balance</strong></li>
                                <li><strong>item_category</strong></li>
                                <li><strong>item_code</strong></li>
                                <li><strong>item_name</strong></li>
                                <li><strong>quantity</strong></li>
                                <li><strong>price</strong></li>
                            </ol>
                            <p><strong>Penting:</strong> Kolom `dealer` harus sesuai dengan kode dealer akun Anda.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
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
                {{-- ID tabel tetap sama untuk JavaScript DataTables --}}
                <table id="services-table" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>No. Invoice</th>
                            <th>Dealer</th>
                            <th>Tanggal Registrasi</th>
                            <th>Pelanggan</th>
                            <th>Plat Nomor</th>
                            <th class="text-right">Total Tagihan</th>
                            <th>Aksi</th>
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
                                <td>
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
            {{-- Footer card dihapus karena paginasi akan ditangani DataTables --}}
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    $(function () {
        // Inisialisasi bs-custom-file-input
        bsCustomFileInput.init();

        // ++ MODIFIKASI 3: Inisialisasi DataTables ++
        $('#services-table').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "paging": true,
            "info": true,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
            }
        });
    });
</script>
@endpush