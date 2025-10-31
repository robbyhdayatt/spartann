@csrf
{{-- Hapus @method('PUT') jika ada --}}
<input type="hidden" name="_method" id="formMethod" value="POST">

{{--
    $barangs (dari controller index) diperlukan untuk dropdown.
    $idPrefix (dari index view) diperlukan untuk ID unik.
    $convert (dummy) diperlukan untuk 'old()' helper.
--}}
@php
    $currentConvert = $convert ?? null;
@endphp

<div class="row">
    <div class="col-md-8 form-group">
        <label for="{{ $idPrefix }}_nama_job">Nama Job (Excel) <span class="text-danger">*</span></label>
        <input type="text" id="{{ $idPrefix }}_nama_job" name="nama_job" class="form-control"
               value="{{ old('nama_job', $currentConvert->nama_job ?? '') }}" required>
    </div>
    <div class="col-md-4 form-group">
        <label for="{{ $idPrefix }}_quantity">Quantity <span class="text-danger">*</span></label>
        <input type="number" id="{{ $idPrefix }}_quantity" name="quantity" class="form-control"
               value="{{ old('quantity', $currentConvert->quantity ?? 1) }}" min="1" required>
    </div>
</div>

<div class="form-group">
    {{-- Dropdown 'Pilih Barang' (menggantikan input part_code, part_name, merk, dll.) --}}
    <label for="{{ $idPrefix }}_part_code">Pilih Barang (dari Master Barang) <span class="text-danger">*</span></label>
    <select name="part_code" id="{{ $idPrefix }}_part_code" class="form-control select2-modal" required style="width: 100%;">
        <option value="">-- Pilih Barang --</option>
        {{-- $barangs dikirim dari Controller@index --}}
        @foreach($barangs as $barang)
            <option value="{{ $barang->part_code }}"
                    {{ old('part_code', $currentConvert->part_code ?? '') == $barang->part_code ? 'selected' : '' }}>
                {{ $barang->part_name }} ({{ $barang->part_code }}) - [{{ $barang->merk ?? 'N/A' }}]
            </option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="{{ $idPrefix }}_keterangan">Keterangan</label>
    <textarea name="keterangan" id="{{ $idPrefix }}_keterangan" class="form-control" rows="2"
              placeholder="Masukkan Keterangan (opsional)">{{ old('keterangan', $currentConvert->keterangan ?? '') }}</textarea>
</div>
