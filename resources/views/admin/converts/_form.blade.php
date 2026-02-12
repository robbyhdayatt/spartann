@php
    $currentConvert = $convert ?? null;
@endphp

<div class="row">
    <div class="col-md-8 form-group">
        <label for="{{ $idPrefix }}_nama_job">Nama Service Package (DPACK) <span class="text-danger">*</span></label>
        <input type="text" id="{{ $idPrefix }}_nama_job" name="nama_job" class="form-control"
               placeholder="Contoh: SERVICE RINGAN"
               value="{{ old('nama_job', $currentConvert->nama_job ?? '') }}" required>
        <span class="invalid-feedback d-block error-text nama_job_error"></span>
    </div>
    <div class="col-md-4 form-group">
        <label for="{{ $idPrefix }}_quantity">Quantity <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_quantity" name="quantity" class="form-control"
               value="{{ old('quantity', $currentConvert->quantity ?? 1) }}" min="1" required>
        <span class="invalid-feedback d-block error-text quantity_error"></span>
    </div>
</div>

<div class="form-group">
    <label for="{{ $idPrefix }}_part_code">Link ke Item <span class="text-danger">*</span></label>
    <select name="part_code" id="{{ $idPrefix }}_part_code" class="form-control select2" style="width: 100%;" required>
        <option value="">-- Pilih Barang --</option>
        @foreach($barangs as $barang)
            <option value="{{ $barang->part_code }}">
                {{ $barang->part_name }} ({{ $barang->part_code }})
            </option>
        @endforeach
    </select>
    <span class="invalid-feedback d-block error-text part_code_error"></span>
</div>

<div class="form-group">
    <label for="{{ $idPrefix }}_keterangan">Keterangan</label>
    <textarea name="keterangan" id="{{ $idPrefix }}_keterangan" class="form-control" rows="2"
              placeholder="Catatan tambahan...">{{ old('keterangan', $currentConvert->keterangan ?? '') }}</textarea>
    <span class="invalid-feedback d-block error-text keterangan_error"></span>
</div>
