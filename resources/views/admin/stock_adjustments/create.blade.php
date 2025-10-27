@extends('adminlte::page')

@section('title', 'Buat Adjusment Stok Baru')

@section('content_header')
    <h1>Buat Adjusment Stok Baru</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.stock-adjustments.store') }}" method="POST">
        @csrf
        <div class="card-body">

            {{-- ++ PERBAIKAN: Input Lokasi ++ --}}
            <div class="form-group">
                <label for="lokasi_id">Lokasi Adjusment</label>
                @php
                    $isSuperUser = auth()->user()->hasRole(['SA', 'PIC']);
                    // Jika user biasa, lokasi sudah ditentukan ($userLokasi). Jika SA/PIC, mereka memilih ($allLokasi).
                    $selectedLokasiId = old('lokasi_id', $userLokasi ? $userLokasi->id : null);
                @endphp

                @if($isSuperUser)
                    {{-- Dropdown untuk SA/PIC --}}
                    <select name="lokasi_id" id="lokasi_id" class="form-control select2 @error('lokasi_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Lokasi --</option>
                        @foreach($allLokasi as $lok)
                            <option value="{{ $lok->id }}" {{ $selectedLokasiId == $lok->id ? 'selected' : '' }}>
                                {{ $lok->nama_lokasi }} ({{ $lok->kode_lokasi }})
                            </option>
                        @endforeach
                    </select>
                @elseif($userLokasi)
                    {{-- Tampilkan nama lokasi untuk user biasa & sertakan input hidden --}}
                    <input type="text" class="form-control" value="{{ $userLokasi->nama_lokasi }} ({{ $userLokasi->kode_lokasi }})" readonly>
                    <input type="hidden" name="lokasi_id" id="lokasi_id" value="{{ $userLokasi->id }}">
                @else
                     {{-- Seharusnya tidak terjadi karena sudah dicek di controller --}}
                     <div class="alert alert-danger">Lokasi tidak ditemukan.</div>
                @endif

                @error('lokasi_id')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>
            {{-- Akhir Input Lokasi --}}


            <div class="form-group">
                <label for="part_id">Part</label>
                <select name="part_id" id="part_id" class="form-control select2 @error('part_id') is-invalid @enderror" required>
                    <option value="">-- Pilih Part --</option>
                    @foreach($parts as $part)
                        <option value="{{ $part->id }}" {{ old('part_id') == $part->id ? 'selected' : '' }}>
                            {{ $part->nama_part }} ({{ $part->kode_part }})
                        </option>
                    @endforeach
                </select>
                @error('part_id')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="rak_id">Rak</label>
                <select name="rak_id" id="rak_id" class="form-control select2 @error('rak_id') is-invalid @enderror" required disabled>
                    <option value="">-- Pilih Lokasi Terlebih Dahulu --</option>
                    {{-- Opsi rak akan diisi oleh JavaScript --}}
                </select>
                 @error('rak_id')
                    <span class="invalid-feedback d-block" role="alert"> {{-- Tambah d-block agar error tampil --}}
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="tipe">Tipe Adjusment</label>
                <select name="tipe" id="tipe" class="form-control @error('tipe') is-invalid @enderror" required>
                    <option value="TAMBAH" {{ old('tipe') == 'TAMBAH' ? 'selected' : '' }}>Tambah Stok</option>
                    <option value="KURANG" {{ old('tipe') == 'KURANG' ? 'selected' : '' }}>Kurang Stok</option>
                </select>
                 @error('tipe')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="jumlah">Jumlah</label>
                <input type="number" name="jumlah" id="jumlah" class="form-control @error('jumlah') is-invalid @enderror" value="{{ old('jumlah') }}" required min="1">
                 @error('jumlah')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="alasan">Alasan Adjusment</label>
                <textarea name="alasan" id="alasan" class="form-control @error('alasan') is-invalid @enderror" rows="3" required>{{ old('alasan') }}</textarea>
                 @error('alasan')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Submit Permintaan</button>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    // Inisialisasi Select2
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    // Fungsi untuk memuat Rak berdasarkan Lokasi
    function loadRaks(lokasiId, selectedRakId = null) {
        const rakSelect = $('#rak_id');
        rakSelect.empty().prop('disabled', true); // Kosongkan dan disable

        if (!lokasiId) {
            rakSelect.append('<option value="">-- Pilih Lokasi Terlebih Dahulu --</option>');
            return; // Keluar jika tidak ada lokasi dipilih
        }

        rakSelect.append('<option value="">Memuat Rak...</option>'); // Placeholder loading

        // URL ke endpoint API, pastikan route 'admin.api.lokasi.raks' ada
        // Anda mungkin perlu membuat route ini di web.php
        // Route::get('/api/lokasi/{lokasi}/raks', [StockAdjustmentController::class, 'getRaksByLokasi'])->name('admin.api.lokasi.raks');
        const url = `/spartann/admin/api/lokasi/${lokasiId}/raks`; // Sesuaikan URL jika perlu

        $.getJSON(url, function(data) {
            rakSelect.empty(); // Kosongkan lagi sebelum mengisi
            if (data && data.length > 0) {
                 rakSelect.append('<option value="">-- Pilih Rak --</option>');
                 $.each(data, function(key, rak) {
                    // Cek jika rak ini harus dipilih (misalnya saat validation error)
                    const isSelected = selectedRakId && rak.id == selectedRakId;
                    rakSelect.append(`<option value="${rak.id}" ${isSelected ? 'selected' : ''}>${rak.nama_rak} (${rak.kode_rak})</option>`);
                });
                rakSelect.prop('disabled', false); // Aktifkan select rak
            } else {
                rakSelect.append('<option value="">-- Tidak ada rak aktif di lokasi ini --</option>');
                 // Tetap disable jika tidak ada rak
            }
        }).fail(function() {
             rakSelect.empty().append('<option value="">Gagal memuat rak.</option>');
             console.error("Error loading raks for lokasi ID:", lokasiId);
        });
    }

    // Event listener saat pilihan lokasi berubah (hanya jika dropdown lokasi ada)
    $('#lokasi_id').on('change', function() {
        const selectedLokasiId = $(this).val();
        loadRaks(selectedLokasiId);
    });

    // Saat halaman dimuat, cek apakah sudah ada lokasi yang terpilih
    // (baik dari user biasa atau dari old() input SA/PIC)
    const initialLokasiId = $('#lokasi_id').val();
    if (initialLokasiId) {
        // Ambil ID rak yang mungkin sudah terpilih dari old input
        const oldRakId = "{{ old('rak_id') }}";
        loadRaks(initialLokasiId, oldRakId);
    } else if (!{{ $isSuperUser ? 'true' : 'false' }}) {
         // Jika user biasa dan tidak ada lokasi awal (error state), tampilkan pesan
         $('#rak_id').empty().append('<option value="">Lokasi user tidak valid.</option>');
    }


});
</script>
@stop

@section('plugins.Select2', true)
