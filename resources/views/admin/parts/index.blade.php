@extends('adminlte::page')

@section('title', 'Item YGP')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Data Item YGP</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Daftar Item YGP</h3>
        <div class="card-tools">
            @can('manage-ygp')
            <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel"></i> Import Excel
            </button>
            <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#createModal">
                <i class="fas fa-plus"></i> Tambah Item
            </button>
            @endcan
        </div>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                {{ session('error') }}
            </div>
        @endif

        <table id="parts-table" class="table table-bordered table-striped" style="width:100%">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th>Kode Part</th>
                    <th>Nama Part</th>
                    <th>Qty Stok</th>
                    <th>Min. Stok</th>
                    <th>Harga Retail</th>
                    <th>Status</th>
                    <th width="12%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data akan dimuat secara asinkron oleh Yajra DataTables -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Create -->
<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('admin.parts.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title" id="createModalLabel">Tambah Item YGP</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Kode Part <span class="text-danger">*</span></label>
                            <input type="text" name="kode_part" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Nama Part <span class="text-danger">*</span></label>
                            <input type="text" name="nama_part" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Stok Minimum <span class="text-danger">*</span></label>
                            <input type="number" name="stok_minimum" class="form-control" value="0" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Qty Stok <span class="text-danger">*</span></label>
                            <input type="number" name="qty_stok" class="form-control" value="0" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Harga Retail <span class="text-danger">*</span></label>
                            <input type="number" name="retail" class="form-control" value="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="is_active" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="formEdit" method="POST">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editModalLabel">Edit Item YGP</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Kode Part <span class="text-danger">*</span></label>
                            <input type="text" name="kode_part" id="edit_kode" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Nama Part <span class="text-danger">*</span></label>
                            <input type="text" name="nama_part" id="edit_nama" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Stok Minimum <span class="text-danger">*</span></label>
                            <input type="number" name="stok_minimum" id="edit_min" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Qty Stok <span class="text-danger">*</span></label>
                            <input type="number" name="qty_stok" id="edit_qty" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Harga Retail <span class="text-danger">*</span></label>
                            <input type="number" name="retail" id="edit_retail" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="is_active" id="edit_active" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Data</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('admin.parts.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title" id="importModalLabel">Import Item YGP</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info pb-0">
                        <p class="mb-2"><i class="fas fa-info-circle"></i> Pastikan format file sesuai dengan template standar sistem.</p>
                        <a href="{{ route('admin.parts.template') }}" class="btn btn-sm btn-outline-primary bg-white mb-3">
                            <i class="fas fa-file-excel"></i> Download Template Excel
                        </a>
                    </div>
                    <div class="form-group mt-3">
                        <label>Pilih File Excel (.xls, .xlsx)</label>
                        <input type="file" name="file" class="form-control" accept=".xls,.xlsx" required>
                    </div>
                    <small class="text-muted">
                        Format header baris pertama hanya perlu: <b>kode_part, nama_part, retail</b>.<br>
                        {{-- <i>*Data baru akan otomatis diatur: Stok Minimum (10), Qty Stok (0), dan Status (Aktif).</i> --}}
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Mulai Import</button>
                </div>
            </div>
        </form>
    </div>
</div>
@stop

@push('js')
<script>
    $(function () {
        $('#parts-table').DataTable({
            processing: true,
            serverSide: true, // Mengaktifkan Server-Side Processing
            responsive: true, 
            autoWidth: false,
            ajax: "{{ route('admin.parts.index') }}", // Menembak ke controller index
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'kode_part', name: 'kode_part'},
                {data: 'nama_part', name: 'nama_part'},
                {data: 'qty_stok', name: 'qty_stok'},
                {data: 'stok_minimum', name: 'stok_minimum'},
                {data: 'retail', name: 'retail'},
                {data: 'is_active', name: 'is_active', searchable: false},
                {data: 'aksi', name: 'aksi', orderable: false, searchable: false, className: 'text-center'},
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
            }
        });

        // HAPUS SCRIPT LAMA BUTTON EDIT DAN GANTI JADI SEPERTI INI:
        // Karena datanya di-render lewat JS (AJAX), kita butuh 'event delegation' untuk tombol Edit
        $('#parts-table').on('click', '.btn-edit', function () {
            let id = $(this).data('id');
            let kode = $(this).data('kode');
            let nama = $(this).data('nama');
            let min = $(this).data('min');
            let qty = $(this).data('qty');
            let retail = $(this).data('retail');
            let active = $(this).data('active');

            $('#edit_kode').val(kode);
            $('#edit_nama').val(nama);
            $('#edit_min').val(min);
            $('#edit_qty').val(qty);
            $('#edit_retail').val(retail);
            $('#edit_active').val(active);

            let url = "{{ url('admin/parts') }}/" + id;
            $('#formEdit').attr('action', url);
            $('#editModal').modal('show');
        });
    });
</script>
@endpush