@extends('adminlte::page')

@section('title', 'Manajemen Rak')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Manajemen Rak</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Rak</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createModal">
                    <i class="fas fa-plus"></i> Tambah Rak
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="raks-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lokasi (Gudang / Dealer)</th>
                        <th>Kode Rak</th>
                        <th>Nama Rak</th>
                        <th>Tipe Rak</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($raks as $rak)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $rak->lokasi->nama_gudang ?? 'N/A' }}</td>
                        <td>{{ $rak->kode_rak }}</td>
                        <td>{{ $rak->nama_rak }}</td>
                        <td>
                            @if($rak->tipe_rak == 'KARANTINA')
                                <span class="badge badge-warning">KARANTINA</span>
                            @else
                                <span class="badge badge-info">PENYIMPANAN</span>
                            @endif
                        </td>
                        <td>
                            @if($rak->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Non-Aktif</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $rak->id }}"
                                    data-gudang_id="{{ $rak->gudang_id }}"
                                    data-kode_rak="{{ $rak->kode_rak }}"
                                    data-nama_rak="{{ $rak->nama_rak }}"
                                    data-tipe_rak="{{ $rak->tipe_rak }}"
                                    data-is_active="{{ $rak->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editModal">
                                Edit
                            </button>
                            <form action="{{ route('admin.raks.destroy', $rak->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus rak ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade" id="createModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tambah Rak Baru</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form action="{{ route('admin.raks.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Lokasi</label>
                            <select class="form-control select2" name="gudang_id" required style="width: 100%;">
                                <option value="" disabled selected>Pilih Lokasi</option>
                                @foreach($lokasi as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_gudang }} ({{$item->kode_gudang}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipe Rak</label>
                            <select class="form-control" name="tipe_rak" required>
                                <option value="PENYIMPANAN">Penyimpanan</option>
                                <option value="KARANTINA">Karantina</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Kode Rak</label><input type="text" class="form-control" name="kode_rak" required></div>
                        <div class="form-group"><label>Nama Rak</label><input type="text" class="form-control" name="nama_rak" required></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Rak</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Lokasi</label>
                            <select class="form-control" id="edit_gudang_id" name="gudang_id" required>
                                @foreach($lokasi as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_gudang }} ({{$item->kode_gudang}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipe Rak</label>
                            <select class="form-control" name="tipe_rak" id="edit_tipe_rak" required>
                                <option value="PENYIMPANAN">Penyimpanan</option>
                                <option value="KARANTINA">Karantina</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Kode Rak</label><input type="text" class="form-control" id="edit_kode_rak" name="kode_rak" required></div>
                        <div class="form-group"><label>Nama Rak</label><input type="text" class="form-control" id="edit_nama_rak" name="nama_rak" required></div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="edit_is_active" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Update</button></div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('css')
<style>
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px) !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; padding-left: .75rem !important; padding-top: .375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px) !important; }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        $('#createModal .select2').select2({ dropdownParent: $('#createModal') });
        $('#raks-table').DataTable({ "responsive": true, "autoWidth": false });

        $('.edit-btn').on('click', function() {
            var id = $(this).data('id');
            var url = "{{ url('admin/raks') }}/" + id;
            $('#editForm').attr('action', url);
            $('#edit_gudang_id').val($(this).data('gudang_id'));
            $('#edit_tipe_rak').val($(this).data('tipe_rak'));
            $('#edit_kode_rak').val($(this).data('kode_rak'));
            $('#edit_nama_rak').val($(this).data('nama_rak'));
            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@stop