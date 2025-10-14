@extends('adminlte::page')

{{-- Tambahkan plugin Datatables dan Daterangepicker --}}
@section('plugins.Datatables', true)
@section('plugins.Daterangepicker', true)

@section('title', 'Daily Report')

@section('content_header')
    <h1>Daily Report</h1>
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

    {{-- Kotak Impor (tidak berubah) --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Impor Laporan Harian</h3></div>
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
            <form action="{{ route('admin.daily-reports.import') }}" method="POST" enctype="multipart/form-data">
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
                    <a href="{{ asset('templates/template_daily_report.xlsx') }}" class="btn btn-success" download><i class="fas fa-download"></i> Download Template</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Data dengan Fitur Filter dan Datatables --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Data Laporan Terimpor</h3></div>
        <div class="card-body">
            {{-- Form untuk Filter --}}
            <form method="GET" action="{{ route('admin.daily-reports.index') }}" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Filter Berdasarkan Tanggal Registrasi:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input type="text" class="form-control" id="dateRange" name="date_range" value="{{ request('date_range') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 align-self-end">
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Filter</button>
                            <a href="{{ route('admin.daily-reports.index') }}" class="btn btn-default">Reset</a>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Tabel Data --}}
            <div class="table-responsive">
                {{-- Beri ID pada tabel --}}
                <table id="reportsTable" class="table table-bordered table-striped">
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
                        @forelse ($reports as $report)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($report->reg_date)->format('d M Y') }}</td>
                                <td>{{ $report->invoice_no }}</td>
                                <td>{{ $report->customer_name }}</td>
                                <td>{{ $report->plate_no }}</td>
                                <td class="text-right">Rp {{ number_format($report->total_payment, 0, ',', '.') }}</td>
                                <td>{{ $report->technician_name }}</td>
                                <td>{{ $report->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.daily-reports.show', $report->id) }}" class="btn btn-sm btn-info" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
            {{-- Paginasi akan tetap berfungsi bersama filter --}}
            {{ $reports->links() }}
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        // Inisialisasi DataTables
        $('#reportsTable').DataTable({
            "paging": false, // Paginasi sudah ditangani Laravel, jadi kita matikan di sini
            "lengthChange": false,
            "searching": true, // Ini akan mengaktifkan kotak pencarian di kanan atas tabel
            "ordering": true,
            "info": false,
            "autoWidth": false,
            "responsive": true,
        });

        // Inisialisasi Date Range Picker
        $('#dateRange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'DD/MM/YYYY'
            }
        });

        $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
        });

        $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });

        // Script untuk menampilkan nama file di input
        $('.custom-file-input').on('change', function(event) {
            var fileName = event.target.files[0].name;
            $(this).next('.custom-file-label').html(fileName);
        });
    });
</script>
@stop
