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
                    <th>Nama Part / Jasa</th>
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
                    <td colspan="6" class="text-center">Belum ada data item.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ======================== MODAL ======================== --}}

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
                    @include('admin.barangs._form', ['idPrefix' => 'create', 'barang' => $barang])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

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
                    @include('admin.barangs._form', ['idPrefix' => 'edit', 'barang' => $barang])
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
            $('#edit_selling_in').val(data.selling_in);
            $('#edit_selling_out').val(data.selling_out);
            $('#edit_retail').val(data.retail);

        }).fail(function() {
            alert('Gagal mengambil data item.');
        });
    });

    @if($errors->any())
        @if(session('edit_form_id'))
            let failedId = {{ session('edit_form_id') }};
            let editButton = $(`.btn-edit[data-update-url*="${failedId}"]`);
            $('#editForm').attr('action', editButton.data('update-url'));
            $('#editBarangModal').modal('show');
        @else
            $('#createBarangModal').modal('show');
        @endif
    @endif

    $('#createBarangModal').on('show.bs.modal', function () {
        let hasCreateErrors = {{ $errors->any() && !session('edit_form_id') ? 'true' : 'false' }};

        if (!hasCreateErrors) {
            $('#createForm')[0].reset();
            $('#create_part_name').val('');
            $('#create_part_code').val('');
            $('#create_merk').val('');
            $('#create_selling_in').val('0');
            $('#create_selling_out').val('0');
            $('#create_retail').val('0');
            $('#createForm .is-invalid').removeClass('is-invalid');
            $('#createForm .alert-danger').remove();
        }
    });

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
