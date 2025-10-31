{{--
    File ini digunakan oleh modal Create dan Edit.
    Variabel $idPrefix (cth: 'create' atau 'edit') WAJIB ada
    untuk membedakan ID field di kedua modal.
--}}
@php
    $currentBarang = $barang ?? null;
@endphp

{{-- Tampilkan error validasi di dalam modal --}}
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
        <label for="{{ $idPrefix }}_part_name">Nama Part / Jasa <span class="text-danger">*</span></label>
        <input type="text" id="{{ $idPrefix }}_part_name" name="part_name" class="form-control"
               value="{{ old('part_name', $currentBarang->part_name ?? '') }}" required>
    </div>
    <div class="col-md-6 form-group">
        <label for="{{ $idPrefix }}_part_code">Kode Part (Unik) <span class="text-danger">*</span></label>
        <input type="text" id="{{ $idPrefix }}_part_code" name="part_code" class="form-control"
               value="{{ old('part_code', $currentBarang->part_code ?? '') }}" required>
    </div>
</div>
<div class="row">
    <div class="col-md-4 form-group">
        <label for="{{ $idPrefix }}_merk">Merk</label>
        <input type="text" id="{{ $idPrefix }}_merk" name="merk" class="form-control"
               value="{{ old('merk', $currentBarang->merk ?? '') }}">
    </div>
    <div class="col-md-4 form-group">
        <label for="{{ $idPrefix }}_harga_modal">Harga Modal (Rp) <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_harga_modal" name="harga_modal" class="form-control"
               value="{{ old('harga_modal', $currentBarang->harga_modal ?? 0) }}" min="0" step="0.01" required>
    </div>
    <div class="col-md-4 form-group">
        <label for="{{ $idPrefix }}_harga_jual">Harga Jual (Rp) <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_harga_jual" name="harga_jual" class="form-control"
               value="{{ old('harga_jual', $currentBarang->harga_jual ?? 0) }}" min="0" step="0.01" required>
    </div>
</div>
