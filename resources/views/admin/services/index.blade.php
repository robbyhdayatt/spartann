@extends('adminlte::page')

@section('plugins.Datatables', true)
@section('plugins.BsCustomFileInput', true)

@section('title', 'Data Service')

@section('content_header')
    <h1>Data Service</h1>
@stop

@section('content')

    {{-- Notifikasi --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    {{-- Kotak Impor --}}
    @can('manage-service')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Impor Data Service</h3></div>
        <div class="card-body">
             <div class="callout callout-info">
                <h5><i class="fas fa-info-circle"></i> Petunjuk Impor Data</h5>
                <ol>
                    <li>Unduh template Excel yang sudah disediakan dengan menekan tombol <strong>"Download Template"</strong>.</li>
                    <li>Isi data sesuai dengan format pada template. Pastikan kolom terisi dengan benar.</li>
                    <li>Sistem akan secara otomatis memeriksa duplikasi data berdasarkan kombinasi <strong>Dealer</strong> dan <strong>No. Invoice</strong>.</li>
                    <li>Data yang sudah ada di database akan dilewati dan tidak akan diimpor kembali.</li>
                    <li>Pilih file yang sudah diisi, lalu tekan tombol <strong>"Impor"</strong>.</li>
                </ol>
            </div>
            <form action="{{ route('admin.services.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="file">Pilih File Excel (.xls, .xlsx)</label>
                    <div class="input-group"><div class="custom-file">
                        <input type="file" class="custom-file-input" id="file" name="file" required accept=".xls,.xlsx">
                        <label class="custom-file-label" for="file">Pilih file...</label>
                    </div></div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-upload"></i> Impor</button>
                    <a href="{{ asset('templates/template_service.xlsx') }}" class="btn btn-success" download><i class="fas fa-download"></i> Download Template</a>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- Tabel Data --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Data Service Terimpor</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="servicesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Tgl. Registrasi</th>
                            <th>No. Invoice</th>
                            <th>Pelanggan</th>
                            <th>Plat No.</th>
                            <th class="text-right">Total Pembayaran</th>
                            <th>Teknisi</th>
                            <th>Tgl. Impor</th>
                            <th style="width: 80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($services as $service)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($service->reg_date)->format('d M Y') }}</td>
                                <td>{{ $service->invoice_no }}</td>
                                <td>{{ $service->customer_name }}</td>
                                <td>{{ $service->plate_no }}</td>
                                <td class="text-right">Rp {{ number_format($service->total_payment, 0, ',', '.') }}</td>
                                <td>{{ $service->technician_name }}</td>
                                <td>{{ $service->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.services.show', $service->id) }}" class="btn btn-sm btn-info" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @can('manage-service')
                                    <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-sm btn-warning" title="Edit / Tambah Part"><i class="fas fa-edit"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Tidak ada data ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer clearfix">
            {{ $services->links() }}
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        bsCustomFileInput.init();
        $('#servicesTable').DataTable({
            "paging": false,
            "lengthChange": false,
            "searching": true,
            "ordering": true,
            "info": false,
            "autoWidth": false,
            "responsive": true,
        });
    });
</script>
@stop
