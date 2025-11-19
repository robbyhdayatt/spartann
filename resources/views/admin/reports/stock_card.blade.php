@extends('adminlte::page')

@section('title', 'Laporan Kartu Stok')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Laporan Kartu Stok</h1>
@stop

@section('content')
    {{-- Form Filter --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Laporan</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.reports.stock-card') }}" method="GET">

                @php
                    $user = Auth::user();
                    // Cek apakah user BISA mem-filter lokasi (SA, PIC, MA)
                    $canFilterLokasi = $user->hasRole(['SA', 'PIC', 'MA']);
                @endphp

                <div class="row align-items-end">
                    {{-- Filter Part --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Spare Part <span class="text-danger">*</span></label>
                            <select name="barang_id" id="barang_id" class="form-control" required>
                                <option></option> {{-- Placeholder untuk Select2 --}}
                                @foreach($barangs as $barang)
                                    <option value="{{ $barang->id }}" {{ request('barang_id') == $barang->id ? 'selected' : '' }}>
                                        {{ $barang->part_name }} ({{ $barang->part_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Filter Lokasi --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Lokasi</label>
                            @if(!$canFilterLokasi && $user->lokasi)
                                {{-- Jika Staf (KG/KC), tampilkan sebagai teks biasa --}}
                                <input type="text" class="form-control" value="{{ $user->lokasi->nama_lokasi }}" readonly>
                                <input type="hidden" name="lokasi_id" value="{{ $user->lokasi_id }}">
                            @else
                                {{-- Jika SA/PIC/MA, tampilkan dropdown --}}
                                <select name="lokasi_id" id="lokasi_id" class="form-control">
                                    <option value="">Semua lokasi</option>
                                    @foreach($lokasis as $lokasi)
                                        {{-- Gunakan $selectedLokasiId dari controller --}}
                                        <option value="{{ $lokasi->id }}" {{ $selectedLokasiId == $lokasi->id ? 'selected' : '' }}>
                                            [{{ $lokasi->tipe }}] {{ $lokasi->nama_lokasi }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>

                    {{-- Filter Tanggal --}}
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                    </div>
                     <div class="col-md-2">
                        <div class="form-group">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                    </div>

                    <div class="col-md-1">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Hasil --}}
    @if(request()->filled('barang_id'))
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Riwayat Pergerakan Stok</h3>
            <div class="card-tools">
                <a href="{{ route('admin.reports.stock-card.export', request()->all()) }}" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="stock-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Keterangan</th>
                        <th class="text-right">Jumlah</th>
                        <th class="text-right">Stok Sebelum</th>
                        <th class="text-right">Stok Sesudah</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $move)
                    <tr>
                        <td>{{ $move->created_at->format('d-m-Y H:i') }}</td>
                        <td>{{ $move->lokasi->nama_lokasi ?? 'N/A' }}</td>
                        <td>{{ $move->keterangan }}</td>
                        <td class="text-right font-weight-bold {{ $move->jumlah > 0 ? 'text-success' : 'text-danger' }}">
                            {{ ($move->jumlah > 0 ? '+' : '') . $move->jumlah }}
                        </td>
                        <td class="text-right">{{ $move->stok_sebelum }}</td>
                        <td class="text-right">{{ $move->stok_sesudah }}</td>
                        <td>{{ $move->user->nama ?? 'Sistem' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">Tidak ada riwayat pergerakan untuk part ini pada periode dan lokasi yang dipilih.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
@stop

{{-- (CSS Anda sudah benar) --}}
@push('css')
<style>
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-top: 0.375rem !important;
        padding-left: 0.75rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        // Inisialisasi Select2
        $('#barang_id').select2({
            placeholder: "--- Pilih Spare Part ---"
        });

        // Hanya inisialisasi Select2 untuk lokasi jika elemennya ada (bukan readonly input)
        if ($('#lokasi_id').is('select')) {
            $('#lokasi_id').select2();
        }

        // Inisialisasi DataTable
        $('#stock-table').DataTable({
            "responsive": true,
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
            "order": [[ 0, "asc" ]] // Urutkan berdasarkan tanggal (kolom pertama)
        });
    });
</script>
@stop
