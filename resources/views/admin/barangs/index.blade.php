@extends('adminlte::page')
@section('title', 'Master Item')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Master Item</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Item</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createBarangModal">
                <i class="fas fa-plus"></i> Tambah Item Baru
            </button>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
         @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <table id="table-barangs" class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>Kode Part</th>
                    <th>Nama Item</th>
                    <th>Merk</th>
                    <th class="text-right">Selling In</th>
                    <th class="text-right">Selling Out</th>
                    <th class="text-right">Retail</th>
                    <th style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($barangs as $barang)
                <tr>
                    <td>{{ $barang->part_code }}</td>
                    <td>{{ $barang->part_name }}</td>
                    <td>{{ $barang->merk ?? '-' }}</td>
                    <td class="text-right">@rupiah($barang->selling_in)</td>
                    <td class="text-right">@rupiah($barang->selling_out)</td>
                    <td class="text-right">@rupiah($barang->retail)</td>
                    <td>
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
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Belum ada data item.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ======================== MODAL CREATE ======================== --}}
<div class="modal fade" id="createBarangModal" tabindex="-1" role="dialog" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('admin.barangs.store') }}" method="POST" id="createForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createModalLabel">Tambah Item Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{-- Form Content Create --}}
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nama Part / Jasa <span class="text-danger">*</span></label>
                            <input type="text" name="part_name" id="create_part_name" class="form-control" required value="{{ old('part_name') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Kode Part (Unik) <span class="text-danger">*</span></label>
                            <input type="text" name="part_code" id="create_part_code" class="form-control" required value="{{ old('part_code') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Merk</label>
                            <input type="text" name="merk" id="create_merk" class="form-control" value="{{ old('merk') }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Selling In (Rp)</label>
                            <input type="text" name="selling_in" id="create_selling_in" class="form-control currency-input" value="{{ old('selling_in') }}">
                            <small class="form-text text-muted">Harga beli dari Supplier</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Selling Out (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="selling_out" id="create_selling_out" class="form-control currency-input" required value="{{ old('selling_out') }}">
                            <small class="form-text text-muted">Harga jual ke Dealer</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Retail (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="retail" id="create_retail" class="form-control currency-input" required value="{{ old('retail') }}">
                            <small class="form-text text-muted">Harga jual ke Konsumen</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ======================== MODAL EDIT ======================== --}}
<div class="modal fade" id="editBarangModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" id="editForm">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{-- Form Content Edit --}}
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
                            <input type="text" name="selling_in" id="edit_selling_in" class="form-control currency-input">
                            <small class="form-text text-muted">Harga beli dari Supplier</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Selling Out (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="selling_out" id="edit_selling_out" class="form-control currency-input" required>
                            <small class="form-text text-muted">Harga jual ke Dealer</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Retail (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="retail" id="edit_retail" class="form-control currency-input" required>
                            <small class="form-text text-muted">Harga jual ke Konsumen</small>
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
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#table-barangs').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "order": [[ 0, "asc" ]]
    });

    // --- LOGIC FORMAT RUPIAH / ANGKA RIBUAN ---
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

    // Event listener untuk input class currency-input
    $(document).on('keyup', '.currency-input', function() {
        $(this).val(formatRupiah($(this).val()));
    });

    // --- LOGIC EDIT MODAL ---
    $('.btn-edit').on('click', function() {
        let url = $(this).data('url');
        let updateUrl = $(this).data('update-url');

        $('#editForm').attr('action', updateUrl);
        $('#editForm')[0].reset();
        $('#editForm .is-invalid').removeClass('is-invalid');

        $.get(url, function(data) {
            $('#edit_part_name').val(data.part_name);
            $('#edit_part_code').val(data.part_code);
            $('#edit_merk').val(data.merk);

            // Format angka dari DB (misal 10000.00) ke format rupiah (10.000)
            $('#edit_selling_in').val(formatRupiah(parseInt(data.selling_in)));
            $('#edit_selling_out').val(formatRupiah(parseInt(data.selling_out)));
            $('#edit_retail').val(formatRupiah(parseInt(data.retail)));

        }).fail(function() {
            alert('Gagal mengambil data item.');
        });
    });

    // --- LOGIC ERROR HANDLING ---
    @if($errors->any())
        @if(session('edit_form_id'))
            let failedId = {{ session('edit_form_id') }};
            let editButton = $(`.btn-edit[data-update-url*="${failedId}"]`);
            // Jika ada error validasi, pastikan format rupiah tetap ada
            $('.currency-input').each(function() {
                $(this).val(formatRupiah($(this).val()));
            });
            $('#editForm').attr('action', editButton.data('update-url'));
            $('#editBarangModal').modal('show');
        @else
            // Format input di modal create jika kembali dengan error
            $('.currency-input').each(function() {
                $(this).val(formatRupiah($(this).val()));
            });
            $('#createBarangModal').modal('show');
        @endif
    @endif

    // Reset Form Create saat modal dibuka (jika tidak ada error)
    $('#createBarangModal').on('show.bs.modal', function () {
        let hasCreateErrors = {{ $errors->any() && !session('edit_form_id') ? 'true' : 'false' }};

        if (!hasCreateErrors) {
            $('#createForm')[0].reset();
            $('#createForm .is-invalid').removeClass('is-invalid');
            $('#createForm .alert-danger').remove();
        }
    });

    // Reset Form Edit saat modal ditutup
    $('#editBarangModal').on('hidden.bs.modal', function () {
        let hasEditErrors = {{ $errors->any() && session('edit_form_id') ? 'true' : 'false' }};
        if (!hasEditErrors) {
            $('#editForm')[0].reset();
            $('#editForm .is-invalid').removeClass('is-invalid');
            $('#editForm .alert-danger').remove();
        }
    });
});
</script>
@stop