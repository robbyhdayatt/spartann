@extends('adminlte::page')

@section('title', 'Buat Permintaan Mutasi')
@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-exchange-alt text-primary mr-2"></i> Buat Permintaan Mutasi</h1>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.stock-mutations.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    {{-- Alert Info --}}
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <i class="icon fas fa-ban"></i> {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-light border-left-primary" role="alert">
                        <i class="fas fa-info-circle text-primary mr-1"></i>
                        Pastikan sisa stok setelah mutasi tidak kurang dari <strong>Batas Minimum</strong>.
                    </div>

                    <div class="row">
                        {{-- 1. Lokasi Asal --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-warehouse mr-1 text-muted"></i> Lokasi Asal (Sumber)</label>
                                @php
                                    $canSelectLokasiAsal = auth()->user()->hasRole(['SA', 'PIC']) || $lokasiAsal->count() > 1;
                                @endphp

                                @if($canSelectLokasiAsal)
                                    <select name="lokasi_asal_id" id="lokasi_asal_id" class="form-control select2" required>
                                        <option value="" selected disabled>-- Pilih Lokasi Asal --</option>
                                        @foreach ($lokasiAsal as $lokasi)
                                            <option value="{{ $lokasi->id }}" {{ old('lokasi_asal_id') == $lokasi->id ? 'selected' : '' }}>
                                                {{ $lokasi->nama_lokasi }} ({{$lokasi->kode_lokasi}})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt text-danger"></i></span>
                                        </div>
                                        <input type="text" class="form-control bg-light" value="{{ $lokasiAsal->first()->nama_lokasi }}" readonly>
                                        <input type="hidden" id="lokasi_asal_id" name="lokasi_asal_id" value="{{ $lokasiAsal->first()->id }}">
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- 2. Lokasi Tujuan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-truck-moving mr-1 text-muted"></i> Lokasi Tujuan</label>
                                <select name="lokasi_tujuan_id" id="lokasi_tujuan_id" class="form-control select2" required>
                                    <option value="" selected disabled>-- Pilih Lokasi Tujuan --</option>
                                    @foreach ($lokasiTujuan as $lokasi)
                                        <option value="{{ $lokasi->id }}" {{ old('lokasi_tujuan_id') == $lokasi->id ? 'selected' : '' }}>
                                            {{ $lokasi->nama_lokasi }} ({{$lokasi->kode_lokasi}})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- 3. Pilih Barang --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-box mr-1 text-muted"></i> Barang / Part</label>
                                <select name="barang_id" id="barang_id" class="form-control select2" required disabled>
                                    <option value="">Pilih Lokasi Asal Dahulu</option>
                                </select>
                            </div>
                        </div>

                        {{-- 4. Input Jumlah --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jumlah Mutasi</label>
                                <div class="input-group">
                                    <input type="number" id="jumlah" name="jumlah" class="form-control" placeholder="0" required min="1" value="{{ old('jumlah') }}">
                                    <div class="input-group-append">
                                        <span class="input-group-text">Pcs</span>
                                    </div>
                                </div>
                                {{-- Feedback Stok --}}
                                <div class="mt-2 d-flex justify-content-between">
                                    <small id="stock-info" class="text-muted font-weight-bold"></small>
                                    <small id="min-stock-info" class="text-danger font-weight-bold d-none"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Keterangan --}}
                    <div class="form-group">
                        <label>Keterangan / Alasan Mutasi</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Permintaan stok darurat dari cabang B...">{{ old('keterangan') }}</textarea>
                    </div>

                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-default shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Batal
                    </a>
                    <button type="submit" id="btn-submit" class="btn btn-primary shadow-sm px-4">
                        <i class="fas fa-paper-plane mr-1"></i> Kirim Permintaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .border-left-primary { border-left: 4px solid #007bff; }
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@push('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    const lokasiAsalSelect = $('#lokasi_asal_id');
    const lokasiTujuanSelect = $('#lokasi_tujuan_id');
    const barangSelect = $('#barang_id');
    const jumlahInput = $('#jumlah');
    const stockInfo = $('#stock-info');
    const minStockInfo = $('#min-stock-info');
    const btnSubmit = $('#btn-submit');

    let currentTotalStock = 0;
    let currentMinStock = 0;

    // --- 1. FILTER LOKASI TUJUAN ---
    function filterLokasiTujuan() {
        const selectedAsalId = lokasiAsalSelect.val();
        const currentTujuanVal = lokasiTujuanSelect.val();

        lokasiTujuanSelect.find('option').each(function() {
            if ($(this).val() === selectedAsalId) {
                $(this).prop('disabled', true);
            } else {
                $(this).prop('disabled', false);
            }
        });

        if (selectedAsalId && currentTujuanVal === selectedAsalId) {
            lokasiTujuanSelect.val('').trigger('change');
        } else {
            lokasiTujuanSelect.trigger('change.select2'); // Refresh UI
        }
    }

    // --- 2. LOAD BARANG (YANG ADA STOK) ---
    function fetchBarangs() {
        const lokasiId = lokasiAsalSelect.val();
        
        barangSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        stockInfo.text('');
        minStockInfo.addClass('d-none');
        jumlahInput.val('');
        
        filterLokasiTujuan();

        if (!lokasiId) {
            barangSelect.html('<option value="">Pilih Lokasi Asal Dahulu</option>');
            return;
        }

        const url = "{{ url('admin/api/lokasi') }}/" + lokasiId + "/parts-with-stock";

        $.getJSON(url, function(items) {
            barangSelect.prop('disabled', false).html('<option value="" selected disabled>-- Pilih Item --</option>');
            
            if (items && items.length > 0) {
                let oldBarangId = "{{ old('barang_id') }}";
                items.forEach(item => {
                    let text = `${item.part_name} (${item.part_code})`;
                    let option = new Option(text, item.id);
                    if (oldBarangId && item.id == oldBarangId) option.selected = true;
                    barangSelect.append(option);
                });
                if (oldBarangId) barangSelect.trigger('change');
            } else {
                barangSelect.html('<option value="">Tidak ada stok tersedia</option>');
            }
        }).fail(() => {
            alert('Gagal memuat data item.');
            barangSelect.html('<option value="">Error</option>');
        });
    }

    // --- 3. LOAD STOK DETAIL ---
    function fetchStock() {
        const barangId = barangSelect.val();
        const lokasiId = lokasiAsalSelect.val();

        stockInfo.text('');
        minStockInfo.addClass('d-none');
        jumlahInput.removeClass('is-invalid is-valid');
        
        if (!barangId || !lokasiId) return;

        const url = `{{ route('admin.api.part.stock-details') }}?lokasi_id=${lokasiId}&barang_id=${barangId}`;

        $.getJSON(url, function(data) {
            currentTotalStock = parseInt(data.total_stock) || 0;
            currentMinStock = parseInt(data.stok_minimum) || 0;

            stockInfo.html(`Tersedia: <strong>${currentTotalStock}</strong> unit`);
            
            if (currentTotalStock > 0) {
                jumlahInput.prop('disabled', false);
            } else {
                jumlahInput.prop('disabled', true);
            }
            validateInput(); // Validasi ulang jika jumlah sudah terisi
        });
    }

    // --- 4. VALIDASI INPUT REAL-TIME ---
    function validateInput() {
        const qty = parseInt(jumlahInput.val()) || 0;
        const sisa = currentTotalStock - qty;

        btnSubmit.prop('disabled', false);
        jumlahInput.removeClass('is-invalid is-valid');
        minStockInfo.addClass('d-none');

        if (qty <= 0) {
            // Belum input valid
            return;
        }

        if (qty > currentTotalStock) {
            jumlahInput.addClass('is-invalid');
            minStockInfo.removeClass('d-none').text(`Stok tidak cukup! (Maks: ${currentTotalStock})`);
            btnSubmit.prop('disabled', true);
        } 
        else if (sisa < currentMinStock) {
            // Peringatan Batas Minimum
            jumlahInput.addClass('is-invalid'); // Merah
            minStockInfo.removeClass('d-none').text(`Melewati Batas Minimum! (Min: ${currentMinStock}, Sisa: ${sisa})`);
            btnSubmit.prop('disabled', true); // Blokir submit
        } 
        else {
            jumlahInput.addClass('is-valid'); // Hijau
        }
    }

    // Events
    lokasiAsalSelect.on('change', fetchBarangs);
    barangSelect.on('change', fetchStock);
    jumlahInput.on('input change', validateInput);

    // Init
    filterLokasiTujuan();
    if (lokasiAsalSelect.val()) fetchBarangs();
});
</script>
@endpush