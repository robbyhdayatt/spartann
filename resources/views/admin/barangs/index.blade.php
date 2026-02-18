@extends('adminlte::page')
@section('title', 'Master Item')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Master Item</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Daftar Item</h3>
        <div class="card-tools">
            @can('manage-barang')
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createBarangModal">
                <i class="fas fa-plus"></i> Tambah Item Baru
            </button>
            @endcan
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>
                {{ session('success') }}
            </x-adminlte-alert>
        @endif
         @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>
                {{ session('error') }}
            </x-adminlte-alert>
        @endif

        <table id="table-barangs" class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th width="15%">Kode Part</th>
                    <th>Nama Item</th>
                    <th width="15%">Merk</th>
                    
                    @can('view-price-selling-in')
                    <th class="text-right bg-light" width="15%">Selling In</th>
                    @endcan
                    
                    @can('view-price-selling-out')
                    <th class="text-right bg-light" width="15%">Selling Out</th>
                    <th class="text-right bg-light" width="15%">Retail</th>
                    @endcan
                    
                    <th class="text-center" width="10%">Status</th> {{-- [BARU] Kolom Status --}}
                    
                    @can('manage-barang')
                    <th class="text-center" width="100px">Aksi</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse($barangs as $barang)
                <tr>
                    <td>{{ $barang->part_code }}</td>
                    <td>{{ $barang->part_name }}</td>
                    <td>{{ $barang->merk ?? '-' }}</td>
                    
                    @can('view-price-selling-in')
                    <td class="text-right">
                        <span class="text-success font-weight-bold">Rp {{ number_format($barang->selling_in, 0, ',', '.') }}</span>
                    </td>
                    @endcan
                    
                    @can('view-price-selling-out')
                    <td class="text-right">Rp {{ number_format($barang->selling_out, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($barang->retail, 0, ',', '.') }}</td>
                    @endcan
                    
                    {{-- [BARU] Badge Status --}}
                    <td class="text-center">
                        @if($barang->is_active)
                            <span class="badge badge-success">Aktif</span>
                        @else
                            <span class="badge badge-secondary">Non-Aktif</span>
                        @endif
                    </td>
                    
                    @can('manage-barang')
                    <td class="text-center">
                        <button class="btn btn-xs btn-warning btn-edit"
                                data-toggle="modal"
                                data-target="#editBarangModal"
                                data-url="{{ route('admin.barangs.show', $barang->id) }}"
                                data-update-url="{{ route('admin.barangs.update', $barang->id) }}"
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.barangs.destroy', $barang->id) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus item ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                    @endcan
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">Belum ada data item.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ======================== MODAL CREATE ======================== --}}
@can('manage-barang')
<div class="modal fade" id="createBarangModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('admin.barangs.store') }}" method="POST" id="createForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Item Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nama Part / Jasa <span class="text-danger">*</span></label>
                            <input type="text" name="part_name" id="create_part_name" class="form-control @error('part_name') is-invalid @enderror" required value="{{ old('part_name') }}">
                            @error('part_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Kode Part (Unik) <span class="text-danger">*</span></label>
                            <input type="text" name="part_code" id="create_part_code" class="form-control @error('part_code') is-invalid @enderror" required value="{{ old('part_code') }}">
                            @error('part_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Merk</label>
                            <input type="text" name="merk" id="create_merk" class="form-control" value="{{ old('merk') }}">
                        </div>
                        
                        {{-- HARGA BELI (SELLING IN) --}}
                        <div class="col-md-3 form-group">
                            <label>Selling In (Rp)</label>
                            @can('view-price-selling-in')
                                <input type="text" name="selling_in" id="create_selling_in" class="form-control currency-input @error('selling_in') is-invalid @enderror" value="{{ old('selling_in', 0) }}">
                                @error('selling_in') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga beli dari Supplier</small>
                        </div>

                        {{-- HARGA JUAL (SELLING OUT & RETAIL) --}}
                        <div class="col-md-3 form-group">
                            <label>Selling Out (Rp)</label>
                            @can('view-price-selling-out')
                                <input type="text" name="selling_out" id="create_selling_out" class="form-control currency-input @error('selling_out') is-invalid @enderror" required value="{{ old('selling_out', 0) }}">
                                @error('selling_out') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga jual ke Dealer</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Retail (Rp)</label>
                            @can('view-price-selling-out')
                                <input type="text" name="retail" id="create_retail" class="form-control currency-input @error('retail') is-invalid @enderror" required value="{{ old('retail', 0) }}">
                                @error('retail') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga jual ke Konsumen</small>
                        </div>
                    </div>
                    
                    {{-- [BARU] Input Status Create (Default Aktif, Hidden) --}}
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- ======================== MODAL EDIT ======================== --}}
@can('manage-barang')
<div class="modal fade" id="editBarangModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" id="editForm">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nama Part / Jasa <span class="text-danger">*</span></label>
                            <input type="text" name="part_name" id="edit_part_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Kode Part (Unik) <span class="text-danger">*</span></label>
                            <input type="text" name="part_code" id="edit_part_code" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Merk</label>
                            <input type="text" name="merk" id="edit_merk" class="form-control">
                        </div>
                        
                        <div class="col-md-3 form-group">
                            <label>Selling In (Rp)</label>
                            @can('view-price-selling-in')
                                <input type="text" name="selling_in" id="edit_selling_in" class="form-control currency-input">
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga beli dari Supplier</small>
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Selling Out (Rp)</label>
                            @can('view-price-selling-out')
                                <input type="text" name="selling_out" id="edit_selling_out" class="form-control currency-input" required>
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga jual ke Dealer</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Retail (Rp)</label>
                            @can('view-price-selling-out')
                                <input type="text" name="retail" id="edit_retail" class="form-control currency-input" required>
                            @else
                                <input type="text" class="form-control" value="Restricted" disabled>
                            @endcan
                            <small class="form-text text-muted">Harga jual ke Konsumen</small>
                        </div>
                    </div>

                    {{-- [BARU] Input Status Edit --}}
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Status Item</label>
                            <select name="is_active" id="edit_is_active" class="form-control">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#table-barangs').DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[ 0, "asc" ]]
    });

    // --- LOGIC FORMAT RUPIAH ---
    function formatRupiah(angka) {
        if (!angka) return '';
        var number_string = angka.toString().replace(/[^,\d]/g, ''),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah;
    }

    // Auto Format Currency Input
    $(document).on('keyup', '.currency-input', function() {
        $(this).val(formatRupiah($(this).val()));
    });

    // --- LOGIC EDIT MODAL ---
    $('.btn-edit').on('click', function() {
        let url = $(this).data('url');
        let updateUrl = $(this).data('update-url');

        $('#editForm').attr('action', updateUrl);
        // Reset Error State
        $('#editForm .is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        $.get(url, function(data) {
            $('#edit_part_name').val(data.part_name);
            $('#edit_part_code').val(data.part_code);
            $('#edit_merk').val(data.merk);
            
            // [BARU] Isi status
            $('#edit_is_active').val(data.is_active ? "1" : "0");

            // Conditional Fill Price (Jika elemen input ada/punya akses)
            if($('#edit_selling_in').length) {
                $('#edit_selling_in').val(formatRupiah(parseInt(data.selling_in)));
            }
            if($('#edit_selling_out').length) {
                $('#edit_selling_out').val(formatRupiah(parseInt(data.selling_out)));
            }
            if($('#edit_retail').length) {
                $('#edit_retail').val(formatRupiah(parseInt(data.retail)));
            }

        }).fail(function() {
            alert('Gagal mengambil data item.');
        });
    });

    // --- LOGIC ERROR HANDLING / RE-OPEN MODAL ---
    @if($errors->any())
        // Re-format rupiah for input with old values
        $('.currency-input').each(function() {
            if($(this).val()) {
                $(this).val(formatRupiah($(this).val()));
            }
        });

        @if(session('edit_form_id'))
            // Jika error dari Edit
            let failedId = {{ session('edit_form_id') }};
            let editButton = $(`.btn-edit[data-update-url*="${failedId}"]`);
            $('#editForm').attr('action', editButton.data('update-url'));
            $('#editBarangModal').modal('show');
        @else
            // Jika error dari Create
            $('#createBarangModal').modal('show');
        @endif
    @endif

    // Reset Form Saat Modal Ditutup/Dibuka
    $('#createBarangModal').on('show.bs.modal', function () {
        if (!{{ $errors->any() ? 'true' : 'false' }}) {
            $('#createForm')[0].reset();
            $('#createForm .is-invalid').removeClass('is-invalid');
        }
    });
});
</script>
@stop