@extends('adminlte::page')

@section('title', 'Buat Permintaan Mutasi')
@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Permintaan Mutasi Stok</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.stock-mutations.store') }}" method="POST">
        @csrf
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
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
                {{-- 1. Pilih Lokasi Asal --}}
                <div class="col-md-4 form-group">
                    <label>Lokasi Asal</label>
                    @php
                        // Cek apakah user punya > 1 lokasi asal atau user adalah SA/PIC
                        $canSelectLokasiAsal = auth()->user()->hasRole(['SA', 'PIC']) || $lokasiAsal->count() > 1;
                    @endphp

                    @if($canSelectLokasiAsal)
                        <select name="lokasi_asal_id" id="lokasi_asal_id" class="form-control select2 @error('lokasi_asal_id') is-invalid @enderror" required>
                            <option value="" selected>Pilih Lokasi Asal</option>
                            @foreach ($lokasiAsal as $lokasi)
                                <option value="{{ $lokasi->id }}" {{ old('lokasi_asal_id') == $lokasi->id ? 'selected' : '' }}>
                                    {{ $lokasi->nama_lokasi }} ({{$lokasi->kode_lokasi}})
                                </option>
                            @endforeach
                        </select>
                    @else
                        {{-- Jika user hanya punya 1 lokasi, otomatis terpilih --}}
                        <input type="text" class="form-control" value="{{ $lokasiAsal->first()->nama_lokasi }} ({{$lokasiAsal->first()->kode_lokasi}})" readonly>
                        <input type="hidden" id="lokasi_asal_id" name="lokasi_asal_id" value="{{ $lokasiAsal->first()->id }}">
                    @endif
                    @error('lokasi_asal_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                {{-- 2. Pilih Item / Barang (Dulu Part) --}}
                <div class="col-md-4 form-group">
                    <label>Part</label>
                    <select name="barang_id" id="barang_id" class="form-control select2 @error('barang_id') is-invalid @enderror" required disabled>
                        <option value="">Pilih Lokasi Asal Dahulu</option>
                    </select>
                    @error('barang_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                {{-- 3. Input Jumlah --}}
                <div class="col-md-4 form-group">
                    <label>Jumlah</label>
                    <input type="number" id="jumlah" name="jumlah" class="form-control @error('jumlah') is-invalid @enderror" placeholder="Jumlah Mutasi" required min="1" value="{{ old('jumlah') }}">
                    <small id="stock-info" class="form-text text-muted font-weight-bold text-primary"></small>
                    @error('jumlah') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="row">
                {{-- 4. Pilih Lokasi Tujuan --}}
                <div class="col-md-4 form-group">
                    <label>Lokasi Tujuan</label>
                    <select name="lokasi_tujuan_id" id="lokasi_tujuan_id" class="form-control select2 @error('lokasi_tujuan_id') is-invalid @enderror" required>
                        <option value="">Pilih Lokasi Tujuan</option>
                        @foreach ($lokasiTujuan as $lokasi)
                            <option value="{{ $lokasi->id }}" {{ old('lokasi_tujuan_id') == $lokasi->id ? 'selected' : '' }}>
                                {{ $lokasi->nama_lokasi }} ({{$lokasi->kode_lokasi}})
                            </option>
                        @endforeach
                    </select>
                    @error('lokasi_tujuan_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                {{-- 5. Keterangan --}}
                <div class="col-md-8 form-group">
                    <label>Keterangan</label>
                    <input type="text" name="keterangan" class="form-control @error('keterangan') is-invalid @enderror" placeholder="Keterangan (opsional)" value="{{ old('keterangan') }}">
                    @error('keterangan') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Buat Permintaan</button>
            <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@push('js')
<script>
$(document).ready(function() {
    // Inisialisasi Select2 dengan tema Bootstrap 4
    $('.select2').select2({ theme: 'bootstrap4' });

    const lokasiAsalSelect = $('#lokasi_asal_id');
    const lokasiTujuanSelect = $('#lokasi_tujuan_id');
    const barangSelect = $('#barang_id'); // ID element select barang
    const jumlahInput = $('#jumlah');
    const stockInfo = $('#stock-info');

    /**
     * Fungsi untuk memfilter lokasi tujuan agar tidak sama dengan lokasi asal
     */
    function filterLokasiTujuan() {
        const selectedAsalId = lokasiAsalSelect.val();
        const currentTujuanVal = lokasiTujuanSelect.val();

        lokasiTujuanSelect.find('option').each(function() {
            const option = $(this);
            const optionVal = option.val();

            if (optionVal) {
                // Enable dulu semua opsi
                option.prop('disabled', false);
            }

            // Disable jika sama dengan lokasi asal
            if (selectedAsalId && optionVal === selectedAsalId) {
                option.prop('disabled', true);
            }
        });

        // Reset jika pilihan saat ini konflik
        if (selectedAsalId && currentTujuanVal === selectedAsalId) {
            lokasiTujuanSelect.val('').trigger('change');
        } else {
            // Refresh tampilan select2
            lokasiTujuanSelect.trigger('change.select2');
        }
    }

    /**
     * Mengambil daftar barang yang memiliki stok di lokasi asal
     */
    function fetchBarangs() {
        const lokasiId = lokasiAsalSelect.val();

        // Reset dropdown barang
        barangSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        stockInfo.text('');
        jumlahInput.removeAttr('max');

        // Filter tujuan setiap kali asal berubah
        filterLokasiTujuan();

        if (!lokasiId) {
            barangSelect.html('<option value="">Pilih Lokasi Asal Dahulu</option>');
            // Reset filter tujuan
            lokasiTujuanSelect.find('option').prop('disabled', false);
            lokasiTujuanSelect.trigger('change.select2');
            return;
        }

        // URL API (Pastikan route di web.php sesuai)
        const url = "{{ url('admin/api/lokasi') }}/" + lokasiId + "/parts-with-stock";

        $.getJSON(url, function(items) {
            barangSelect.prop('disabled', false).html('<option value="">Pilih Item</option>');

            if (items && items.length > 0) {
                let oldBarangId = "{{ old('barang_id') }}";

                items.forEach(item => {
                    let text = `${item.part_name} (${item.part_code})`;
                    if(item.merk) text += ` - ${item.merk}`;

                    let option = new Option(text, item.id);

                    if (oldBarangId && item.id == oldBarangId) {
                        option.selected = true;
                    }
                    barangSelect.append(option);
                });

                // Trigger change untuk load stok jika ada old input
                if (oldBarangId) {
                    barangSelect.trigger('change');
                }
            } else {
                barangSelect.html('<option value="">Tidak ada stok tersedia</option>');
            }
        }).fail(function() {
            alert('Gagal memuat data item. Pastikan koneksi aman.');
            barangSelect.html('<option value="">Error memuat item</option>');
        });
    }

    /**
     * Mengambil detail stok spesifik untuk barang terpilih
     */
    function fetchStock() {
        const barangId = barangSelect.val();
        const lokasiId = lokasiAsalSelect.val();

        stockInfo.text('');
        jumlahInput.removeAttr('max');
        // Jangan reset val() agar user tidak perlu mengetik ulang jika hanya ganti barang

        if (!barangId || !lokasiId) return;

        // URL API Detail Stok
        const url = `{{ route('admin.api.part.stock-details') }}?lokasi_id=${lokasiId}&barang_id=${barangId}`;

        $.getJSON(url, function(data) {
            const totalStock = parseInt(data.total_stock) || 0;

            stockInfo.text(`Stok tersedia: ${totalStock} unit`);

            if (totalStock > 0) {
                jumlahInput.attr('max', totalStock);
            } else {
                jumlahInput.attr('max', 0);
                // Opsional: beri peringatan jika stok 0 tapi lolos filter list
            }
        }).fail(() => stockInfo.text('Gagal memuat info stok.'));
    }

    // --- Event Listeners ---

    // Saat lokasi asal berubah
    lokasiAsalSelect.on('change', fetchBarangs);

    // Saat barang berubah
    barangSelect.on('change', fetchStock);

    // Initial Load (Saat halaman dimuat pertama kali atau setelah error validasi)
    filterLokasiTujuan();
    if (lokasiAsalSelect.val()) {
        fetchBarangs();
    }
});
</script>
@endpush
