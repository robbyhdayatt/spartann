@extends('adminlte::page')

@section('title', 'Buat Retur Penjualan')

@section('content_header')
    <h1><i class="fas fa-undo-alt mr-2"></i>Buat Retur Penjualan</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>
                {{ session('success') }}
            </x-adminlte-alert>
        @endif

        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>
                {{ session('error') }}
            </x-adminlte-alert>
        @endif

        <div class="card card-outline card-primary">
            <form action="{{ route('admin.sales-returns.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="penjualan-select">Pilih Faktur Penjualan <span class="text-danger">*</span></label>
                                <select id="penjualan-select" name="penjualan_id" class="form-control @error('penjualan_id') is-invalid @enderror" required>
                                    <option value="">--- Cari Nomor Faktur ---</option>
                                    @foreach($penjualans as $p)
                                        <option value="{{ $p->id }}" {{ old('penjualan_id') == $p->id ? 'selected' : '' }}>
                                            {{ $p->nomor_faktur }} ({{ $p->konsumen->nama_konsumen }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('penjualan_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_retur">Tanggal Retur <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" value="{{ old('tanggal_retur', now()->format('Y-m-d')) }}" required>
                                @error('tanggal_retur') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="catatan">Catatan / Alasan Retur</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Barang cacat produksi">{{ old('catatan') }}</textarea>
                    </div>

                    <hr>
                    <h5 class="text-primary mb-3"><i class="fas fa-list mr-2"></i>Daftar Item Faktur</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped border">
                            <thead class="bg-light">
                                <tr>
                                    <th>Nama Part / Suku Cadang</th>
                                    <th class="text-center" style="width: 15%">Qty Jual</th>
                                    <th class="text-center" style="width: 15%">Sudah Retur</th>
                                    <th class="text-center" style="width: 20%">Jumlah Retur Baru</th>
                                </tr>
                            </thead>
                            <tbody id="return-items-table">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Silakan pilih nomor faktur terlebih dahulu.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-right">
                    <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-default">Kembali</a>
                    <button type="submit" class="btn btn-primary ml-2">
                        <i class="fas fa-save mr-1"></i> Simpan Data Retur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('plugins.Select2', true)

@section('js')
<script>
$(document).ready(function() {
    $('#penjualan-select').select2({ theme: 'bootstrap4' });

    $('#penjualan-select').on('change', function() {
        let id = $(this).val();
        let tbody = $('#return-items-table');

        if (!id) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Silakan pilih nomor faktur terlebih dahulu.</td></tr>');
            return;
        }

        tbody.html('<tr><td colspan="4" class="text-center py-4"><i class="fas fa-sync fa-spin"></i> Sedang mengambil data...</td></tr>');

        $.get("{{ url('admin/api/penjualans') }}/" + id + "/returnable-items", function(items) {
            tbody.empty();
            if (items.length === 0) {
                tbody.html('<tr><td colspan="4" class="text-center text-warning py-4">Semua item dalam faktur ini sudah diretur habis.</td></tr>');
            } else {
                items.forEach(function(item) {
                    tbody.append(`
                        <tr>
                            <td>
                                <strong>${item.barang.part_name}</strong><br>
                                <small class="text-muted">${item.barang.part_code}</small>
                            </td>
                            <td class="text-center">${item.qty_jual}</td>
                            <td class="text-center text-danger">${item.qty_diretur}</td>
                            <td>
                                <div class="input-group">
                                    <input type="number" name="items[${item.id}][qty_retur]" 
                                           class="form-control text-center" 
                                           value="0" min="0" max="${item.max_returnable}" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">Maks: ${item.max_returnable}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            }
        }).fail(function() {
            tbody.html('<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat data. Silakan refresh halaman.</td></tr>');
        });
    });
});
</script>
@stop