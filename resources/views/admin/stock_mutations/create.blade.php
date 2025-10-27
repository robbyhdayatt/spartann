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
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Lokasi Asal</label>
                    @php
                        // Cek apakah user punya > 1 lokasi asal atau user adalah SA/PIC
                        $canSelectLokasiAsal = auth()->user()->hasRole(['SA', 'PIC']) || $lokasiAsal->count() > 1;
                        $singleLokasiAsalId = !$canSelectLokasiAsal ? $lokasiAsal->first()->id : null;
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
                        <input type="text" class="form-control" value="{{ $lokasiAsal->first()->nama_lokasi }} ({{$lokasiAsal->first()->kode_lokasi}})" readonly>
                        <input type="hidden" id="lokasi_asal_id" name="lokasi_asal_id" value="{{ $lokasiAsal->first()->id }}">
                    @endif
                     @error('lokasi_asal_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-4 form-group">
                    <label>Part yang akan dimutasi</label>
                    <select name="part_id" id="part_id" class="form-control select2 @error('part_id') is-invalid @enderror" required disabled>
                        <option value="">Pilih Lokasi Asal Dahulu</option>
                    </select>
                     @error('part_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label>Jumlah</label>
                    <input type="number" id="jumlah" name="jumlah" class="form-control @error('jumlah') is-invalid @enderror" placeholder="Jumlah Mutasi" required min="1" value="{{ old('jumlah') }}">
                    <small id="stock-info" class="form-text text-muted"></small>
                     @error('jumlah') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Lokasi Tujuan</label>
                    <select name="lokasi_tujuan_id" id="lokasi_tujuan_id" class="form-control select2 @error('lokasi_tujuan_id') is-invalid @enderror" required>
                        {{-- ++ PERBAIKAN: Hapus 'disabled' dari option pertama ++ --}}
                        <option value="">Pilih Lokasi Tujuan</option>
                        @foreach ($lokasiTujuan as $lokasi)
                            {{-- Kita akan filter via JS, tapi tetap tampilkan semua di awal --}}
                            <option value="{{ $lokasi->id }}" {{ old('lokasi_tujuan_id') == $lokasi->id ? 'selected' : '' }}>
                                {{ $lokasi->nama_lokasi }} ({{$lokasi->kode_lokasi}})
                            </option>
                        @endforeach
                    </select>
                     @error('lokasi_tujuan_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-8 form-group">
                    <label>Keterangan</label>
                    <input type="text" name="keterangan" class="form-control @error('keterangan') is-invalid @enderror" placeholder="Keterangan (opsional)" value="{{ old('keterangan') }}">
                     @error('keterangan') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Buat Permintaan</button>
            <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' }); // Tambahkan theme

    const lokasiAsalSelect = $('#lokasi_asal_id');
    const lokasiTujuanSelect = $('#lokasi_tujuan_id'); // Referensi ke select tujuan
    const partSelect = $('#part_id');
    const jumlahInput = $('#jumlah');
    const stockInfo = $('#stock-info');

    // ++ BARU: Fungsi untuk memfilter lokasi tujuan ++
    function filterLokasiTujuan() {
        const selectedAsalId = lokasiAsalSelect.val();
        const currentTujuanVal = lokasiTujuanSelect.val(); // Simpan pilihan tujuan saat ini

        lokasiTujuanSelect.find('option').each(function() {
            const option = $(this);
            const optionVal = option.val();

            // Enable semua opsi dulu (kecuali placeholder jika ada)
            if (optionVal) {
                option.prop('disabled', false);
            }

            // Jika ada lokasi asal terpilih DAN nilai option tujuan = nilai asal, disable
            if (selectedAsalId && optionVal === selectedAsalId) {
                option.prop('disabled', true);
            }
        });

        // Jika pilihan tujuan saat ini menjadi disabled karena sama dengan asal, reset pilihan tujuan
        if (selectedAsalId && currentTujuanVal === selectedAsalId) {
             lokasiTujuanSelect.val('').trigger('change'); // Reset Select2
        } else {
             // Re-trigger change agar Select2 update tampilan disable/enable
             lokasiTujuanSelect.trigger('change.select2');
        }
    }


    function fetchParts() {
        const lokasiId = lokasiAsalSelect.val();
        partSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        stockInfo.text('');
        jumlahInput.removeAttr('max');

        // ++ PANGGIL FUNGSI FILTER TUJUAN DI SINI JUGA ++
        filterLokasiTujuan();

        if (!lokasiId) {
            partSelect.html('<option value="">Pilih Lokasi Asal Dahulu</option>');
             // Pastikan tujuan di-enable semua jika asal dikosongkan
             lokasiTujuanSelect.find('option').prop('disabled', false);
             lokasiTujuanSelect.trigger('change.select2'); // Update tampilan Select2
            return;
        }

        // Pastikan route API ini ada dan mengembalikan JSON parts yang punya stok > 0
        const url = "{{ url('admin/api/lokasi') }}/" + lokasiId + "/parts-with-stock";
        $.getJSON(url, function(parts) {
            partSelect.prop('disabled', false).html('<option value="">Pilih Part</option>');
            if (parts && parts.length > 0) { // Tambah check 'parts'
                 // Simpan part_id yang lama jika ada (untuk error validation)
                 let oldPartId = "{{ old('part_id') }}";
                parts.forEach(part => {
                    let option = new Option(`${part.nama_part} (${part.kode_part})`, part.id);
                    // Tandai selected jika cocok dengan old input
                    if (oldPartId && part.id == oldPartId) {
                        option.selected = true;
                    }
                    partSelect.append(option);
                });
                // Jika ada oldPartId, trigger change untuk load stok awal
                 if (oldPartId) {
                    partSelect.trigger('change');
                 }
            } else {
                partSelect.html('<option value="">Tidak ada part tersedia</option>');
            }
        }).fail(function() {
            alert('Gagal memuat data part.');
            partSelect.html('<option value="">Error memuat part</option>');
        });
    }

    function fetchStock() {
        const partId = partSelect.val();
        const lokasiId = lokasiAsalSelect.val();
        stockInfo.text('');
        jumlahInput.removeAttr('max').val(''); // Reset jumlah input

        if (!partId || !lokasiId) return;

        // Pastikan route API ini ada dan mengembalikan JSON { total_stock: xxx }
        const url = `{{ route('admin.api.part.stock-details') }}?lokasi_id=${lokasiId}&part_id=${partId}`;
        $.getJSON(url, function(data) {
            stockInfo.text(`Stok tersedia: ${data.total_stock || 0}`);
            if (data.total_stock && data.total_stock > 0) { // Hanya set max jika > 0
                jumlahInput.attr('max', data.total_stock);
            } else {
                 jumlahInput.attr('max', 0); // Set max 0 jika tidak ada stok
                 // Opsional: disable input jumlah jika tidak ada stok
                 // jumlahInput.prop('disabled', true);
            }
        }).fail(() => stockInfo.text('Gagal memuat info stok.'));
    }

    // Listener ketika lokasi asal berubah
    lokasiAsalSelect.on('change', fetchParts); // fetchParts sudah memanggil filterLokasiTujuan

    // Listener ketika part berubah
    partSelect.on('change', fetchStock);

    // Initial load jika ada old input atau lokasi asal tunggal
    // ++ PANGGIL filterLokasiTujuan() saat load halaman ++
    filterLokasiTujuan();
    if (lokasiAsalSelect.val()) {
        fetchParts(); // fetchParts akan memanggil fetchStock jika ada old('part_id')
    }
});
</script>
@endpush
