@php
    $currentBarang = $barang ?? null;
@endphp

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
    <div class="col-md-3 form-group">
        <label for="{{ $idPrefix }}_merk">Merk</label>
        <input type="text" id="{{ $idPrefix }}_merk" name="merk" class="form-control"
               value="{{ old('merk', $currentBarang->merk ?? '') }}">
    </div>

    {{-- 1. Selling In (Modal) --}}
    <div class="col-md-3 form-group">
        <label for="{{ $idPrefix }}_selling_in">Selling In (Rp)</label>
        {{-- Perubahan: Value default '' (kosong), bukan 0 --}}
        <input type="number" id="{{ $idPrefix }}_selling_in" name="selling_in" class="form-control"
               value="{{ old('selling_in', $currentBarang->selling_in ?? '') }}" min="0" step="0.01">
        {{-- Tambahan Keterangan --}}
        <small class="form-text text-muted">
            Harga beli MD Part ke Supplier
        </small>
    </div>

    {{-- 2. Selling Out (Harga Transfer) --}}
    <div class="col-md-3 form-group">
        <label for="{{ $idPrefix }}_selling_out">Selling Out (Rp) <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_selling_out" name="selling_out" class="form-control"
               value="{{ old('selling_out', $currentBarang->selling_out ?? '') }}" min="0" step="0.01" required>
        {{-- Tambahan Keterangan --}}
        <small class="form-text text-muted">
            Harga jual MD Part ke Dealer
        </small>
    </div>

    {{-- 3. Retail (HET) --}}
    <div class="col-md-3 form-group">
        <label for="{{ $idPrefix }}_retail">Retail (Rp) <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_retail" name="retail" class="form-control"
               value="{{ old('retail', $currentBarang->retail ?? '') }}" min="0" step="0.01" required>
        {{-- Tambahan Keterangan --}}
        <small class="form-text text-muted">
            Harga jual Dealer ke Konsumen
        </small>
    </div>
</div>
