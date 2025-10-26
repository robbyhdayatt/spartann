@csrf
{{-- Hapus @method('PUT') jika ada --}}
<div class="row">
    <div class="col-md-6">
        <x-adminlte-input name="original_part_code" label="Original Part Code" placeholder="Masukkan Original Part Code" fgroup-class="mb-3" value="{{ old('original_part_code', $convert->original_part_code ?? '') }}" required/>
        <x-adminlte-input name="nama_job" label="Nama Job (Excel)" placeholder="Masukkan Nama Job dari Excel" fgroup-class="mb-3" value="{{ old('nama_job', $convert->nama_job ?? '') }}" required/>
        <x-adminlte-input name="part_name" label="Nama Part Hasil Convert" placeholder="Masukkan Nama Part" fgroup-class="mb-3" value="{{ old('part_name', $convert->part_name ?? '') }}" required/>
        <x-adminlte-input name="merk" label="Merk" placeholder="Masukkan Merk" fgroup-class="mb-3" value="{{ old('merk', $convert->merk ?? '') }}"/>
    </div>
    <div class="col-md-6">
        <x-adminlte-input name="part_code_input" label="Part Code Input (Hasil Convert)" placeholder="Masukkan Part Code Input" fgroup-class="mb-3" value="{{ old('part_code_input', $convert->part_code_input ?? '') }}" required/>
        <x-adminlte-input type="number" name="quantity" label="Quantity" placeholder="Masukkan Quantity" fgroup-class="mb-3" value="{{ old('quantity', $convert->quantity ?? 1) }}" min="1" required/>
        <x-adminlte-input type="number" name="harga_modal" label="Harga Modal" placeholder="Masukkan Harga Modal" fgroup-class="mb-3" value="{{ old('harga_modal', $convert->harga_modal ?? 0) }}" min="0" step="any" required/> {{-- Ganti step 0.01 ke any --}}
        <x-adminlte-input type="number" name="harga_jual" label="Harga Jual" placeholder="Masukkan Harga Jual" fgroup-class="mb-3" value="{{ old('harga_jual', $convert->harga_jual ?? 0) }}" min="0" step="any" required/> {{-- Ganti step 0.01 ke any --}}
    </div>
</div>
<x-adminlte-textarea name="keterangan" label="Keterangan" placeholder="Masukkan Keterangan (opsional)" fgroup-class="mb-3">{{ old('keterangan', $convert->keterangan ?? '') }}</x-adminlte-textarea>

{{-- Tombol simpan saja, tombol kembali dihapus --}}
{{-- <div class="mt-3">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Simpan
    </button>
</div> --}}

{{-- Field _method untuk update akan ditambahkan via JS --}}
<input type="hidden" name="_method" id="formMethod" value="POST">

{{-- Tambahkan div untuk menampilkan error validasi --}}
<div id="validation-errors" class="alert alert-danger" style="display: none;">
    <ul class="mb-0"></ul>
</div>