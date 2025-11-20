@extends('adminlte::page')

@section('title', 'Buat Stock Adjustment')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Permintaan Stock Adjustment</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.stock-adjustments.store') }}" method="POST">
        @csrf
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="lokasi_id">Lokasi Adjustment <span class="text-danger">*</span></label>

                        @if($userLokasi)
                            {{-- Jika user terikat lokasi (Dealer) --}}
                            <input type="text" class="form-control" value="{{ $userLokasi->nama_lokasi }} ({{ $userLokasi->kode_lokasi }})" readonly>
                            <input type="hidden" name="lokasi_id" id="lokasi_id" value="{{ $userLokasi->id }}">
                        @else
                            {{-- Jika user Pusat/Global --}}
                            <select name="lokasi_id" id="lokasi_id" class="form-control select2" required>
                                <option value="">-- Pilih Lokasi --</option>
                                @foreach($allLokasi as $loc)
                                    <option value="{{ $loc->id }}" {{ old('lokasi_id') == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->nama_lokasi }} ({{ $loc->kode_lokasi }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="rak_id">Pilih Rak <span class="text-danger">*</span></label>
                        <select name="rak_id" id="rak_id" class="form-control select2" required disabled>
                            <option value="">-- Pilih Lokasi Dahulu --</option>
                        </select>
                        <small class="text-muted" id="rak-loading" style="display:none;">Memuat data rak...</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="barang_id">Pilih Barang <span class="text-danger">*</span></label>
                        <select name="barang_id" id="barang_id" class="form-control select2" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($barangs as $item)
                                <option value="{{ $item->id }}" {{ old('barang_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->part_name }} ({{ $item->part_code }}) {{ $item->merk ? '- '.$item->merk : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="tipe">Tipe Adjustment <span class="text-danger">*</span></label>
                        <select name="tipe" id="tipe" class="form-control" required>
                            <option value="KURANG" {{ old('tipe') == 'KURANG' ? 'selected' : '' }}>KURANG (Stok Hilang/Rusak)</option>
                            <option value="TAMBAH" {{ old('tipe') == 'TAMBAH' ? 'selected' : '' }}>TAMBAH (Stok Ketemu/Lebih)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="jumlah">Jumlah <span class="text-danger">*</span></label>
                        <input type="number" name="jumlah" id="jumlah" class="form-control" min="1" value="{{ old('jumlah', 1) }}" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="alasan">Alasan / Keterangan <span class="text-danger">*</span></label>
                <textarea name="alasan" id="alasan" class="form-control" rows="3" placeholder="Contoh: Barang rusak saat display, atau selisih stok opname" required>{{ old('alasan') }}</textarea>
            </div>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Simpan Permintaan</button>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@push('css')
<style>
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px) !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; padding-left: .75rem !important; padding-top: .375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px) !important; }
</style>
@endpush

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    const lokasiSelect = $('#lokasi_id');
    const rakSelect = $('#rak_id');
    const loadingLabel = $('#rak-loading');

    // Fungsi Load Rak via AJAX
    function loadRaks(lokasiId) {
        if (!lokasiId) {
            rakSelect.empty().append('<option value="">-- Pilih Lokasi Dahulu --</option>').prop('disabled', true);
            return;
        }

        loadingLabel.show();
        rakSelect.prop('disabled', true);

        // URL API: pastikan route ini ada di web.php (Route::get('/api/lokasi/{lokasi}/raks', ...))
        let url = "{{ url('admin/api/lokasi') }}/" + lokasiId + "/raks";

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                rakSelect.empty();
                if (data.length > 0) {
                    rakSelect.append('<option value="">-- Pilih Rak --</option>');
                    $.each(data, function(key, value) {
                        let selected = "{{ old('rak_id') }}" == value.id ? 'selected' : '';
                        rakSelect.append('<option value="' + value.id + '" '+selected+'>' + value.kode_rak + ' (' + value.nama_rak + ')</option>');
                    });
                    rakSelect.prop('disabled', false);
                } else {
                    rakSelect.append('<option value="">Tidak ada rak di lokasi ini</option>');
                }
            },
            error: function(xhr) {
                console.error(xhr);
                alert('Gagal memuat data rak. Periksa koneksi atau refresh halaman.');
            },
            complete: function() {
                loadingLabel.hide();
            }
        });
    }

    // 1. Listener saat Lokasi Berubah (Untuk User Pusat)
    if (lokasiSelect.is('select')) {
        lokasiSelect.on('change', function() {
            loadRaks($(this).val());
        });
        // Jika ada old value (misal setelah error validasi), trigger change
        if(lokasiSelect.val()) {
            loadRaks(lokasiSelect.val());
        }
    }
    // 2. Listener saat Halaman Load (Untuk User Dealer dengan Input Hidden)
    else {
        let initialLokasiId = lokasiSelect.val(); // Ambil value dari input hidden
        if (initialLokasiId) {
            loadRaks(initialLokasiId);
        } else {
            // Fallback jika entah kenapa kosong
            rakSelect.append('<option value="">Lokasi User Tidak Valid</option>');
        }
    }
});
</script>
@stop
