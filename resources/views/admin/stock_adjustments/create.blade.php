@extends('adminlte::page')

@section('title', 'Buat Adjusment Stok')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Permintaan Adjusment Stok</h1>
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
                <div class="col-md-6 form-group">
                    <label>Lokasi</label>
                    {{-- Logika ini memastikan user hanya bisa memilih lokasinya sendiri --}}
                    @if($lokasi)
                        <input type="text" class="form-control" value="{{ $lokasi->nama_gudang }}" readonly>
                        <input type="hidden" id="lokasi_id" name="gudang_id" value="{{ $lokasi->id }}">
                    @else
                        <p class="form-control-static text-danger">Anda tidak terasosiasi dengan lokasi manapun.</p>
                    @endif
                </div>

                <div class="col-md-6 form-group">
                    <label>Rak</label>
                    <select name="rak_id" id="rak_id" class="form-control select2" required>
                        <option value="">-- Pilih Lokasi Terlebih Dahulu --</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Part</label>
                <select name="part_id" class="form-control select2" required>
                    <option value="" disabled selected>Pilih Part</option>
                    @foreach($parts as $part)
                        <option value="{{ $part->id }}">{{ $part->nama_part }} ({{$part->kode_part}})</option>
                    @endforeach
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Tipe Adjusment</label>
                    <select name="tipe" class="form-control" required>
                        <option value="TAMBAH">Penambahan (+)</option>
                        <option value="KURANG">Pengurangan (-)</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Jumlah</label>
                    <input type="number" name="jumlah" class="form-control" min="1" required>
                </div>
            </div>
            <div class="form-group">
                <label>Alasan</label>
                <textarea name="alasan" class="form-control" rows="3" required placeholder="Contoh: Hasil stock opname, barang rusak, dll."></textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Ajukan Permintaan</button>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    function fetchRaks(lokasiId) {
        const rakSelect = $('#rak_id');
        if (lokasiId) {
            // PERBAIKAN: Gunakan route dan parameter yang benar
            let url = "{{ route('admin.api.lokasi.raks', ['lokasi' => ':id']) }}";
            url = url.replace(':id', lokasiId);

            rakSelect.prop('disabled', true).html('<option value="">Memuat...</option>');

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    rakSelect.prop('disabled', false).empty().append('<option value="">-- Pilih Rak --</option>');
                    if (data.length > 0) {
                        $.each(data, function(key, value) {
                            rakSelect.append('<option value="' + value.id + '">' + value.nama_rak + ' (' + value.kode_rak + ')</option>');
                        });
                    } else {
                        rakSelect.html('<option value="">-- Tidak ada rak di lokasi ini --</option>');
                    }
                },
                error: function() {
                    rakSelect.prop('disabled', false).html('<option value="">-- Gagal memuat data --</option>');
                }
            });
        }
    }

    // Ambil ID dari hidden input dan panggil fungsi
    const lokasiId = $('#lokasi_id').val();
    if (lokasiId) {
        fetchRaks(lokasiId);
    }
});
</script>
@endpush