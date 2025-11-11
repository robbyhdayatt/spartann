@extends('adminlte::page')

@section('title', 'Manajemen Pengguna')
@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Manajemen Pengguna</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Pengguna</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createModal">
                    <i class="fas fa-plus"></i> Tambah Pengguna
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="users-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Jabatan</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th style="width: 150px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->nama }} <br><small class="text-muted">NIK: {{ $user->nik }}</small></td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->jabatan->nama_jabatan ?? 'N/A' }}</td>
                        {{-- PERUBAHAN: Memanggil relasi 'lokasi' --}}
                        <td>{{ $user->lokasi->nama_lokasi ?? 'Global' }}</td>
                        <td>
                            @if($user->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Non-Aktif</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $user->id }}"
                                    data-user='@json($user)'
                                    data-toggle="modal"
                                    data-target="#editModal">
                                Edit
                            </button>
                            @if(auth()->id() !== $user->id)
                            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade" id="createModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tambah Pengguna Baru</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Nama Lengkap</label><input type="text" class="form-control" name="nama" required value="{{ old('nama') }}"></div>
                            <div class="col-md-6 form-group"><label>NIK</label><input type="text" class="form-control" name="nik" required value="{{ old('nik') }}"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Jabatan</label>
                                <select class="form-control select2" name="jabatan_id" required style="width: 100%;">
                                    <option value="" disabled selected>Pilih Jabatan</option>
                                    @foreach($jabatans as $jabatan)
                                        <option value="{{ $jabatan->id }}">{{ $jabatan->nama_jabatan }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Lokasi</label>
                                <select class="form-control select2" name="lokasi_id" style="width: 100%;">
                                    <option value="">Tidak Terikat Lokasi (Global)</option>
                                    @foreach($lokasi as $item)
                                        <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{ $item->kode_lokasi }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                             <div class="col-md-6 form-group"><label>Username</label><input type="text" class="form-control" name="username" required value="{{ old('username') }}"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Password</label><input type="password" class="form-control" name="password" required></div>
                            <div class="col-md-6 form-group"><label>Konfirmasi Password</label><input type="password" class="form-control" name="password_confirmation" required></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Pengguna</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                         <div class="row">
                            <div class="col-md-6 form-group"><label>Nama Lengkap</label><input type="text" class="form-control" id="edit_nama" name="nama" required></div>
                            <div class="col-md-6 form-group"><label>NIK</label><input type="text" class="form-control" id="edit_nik" name="nik" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Jabatan</label>
                                <select class="form-control" id="edit_jabatan_id" name="jabatan_id" required>
                                    @foreach($jabatans as $jabatan)
                                        <option value="{{ $jabatan->id }}">{{ $jabatan->nama_jabatan }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Lokasi</label>
                                <select class="form-control" id="edit_lokasi_id" name="lokasi_id">
                                    <option value="">Tidak Terikat Lokasi (Global)</option>
                                    @foreach($lokasi as $item)
                                        <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{ $item->kode_lokasi }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group"><label>Username</label><input type="text" class="form-control" id="edit_username" name="username" required></div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="edit_is_active" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                        <hr>
                        <p class="text-muted">Kosongkan password jika tidak ingin mengubahnya.</p>
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Password Baru</label><input type="password" class="form-control" name="password"></div>
                            <div class="col-md-6 form-group"><label>Konfirmasi Password Baru</label><input type="password" class="form-control" name="password_confirmation"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Update</button></div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('#users-table').DataTable({ "responsive": true, "autoWidth": false });
    $('#createModal .select2').select2({ dropdownParent: $('#createModal') });
    // Untuk edit modal, select2 diinisialisasi saat modal dibuka jika diperlukan

    $('.edit-btn').on('click', function() {
        var user = $(this).data('user');
        var url = "{{ url('admin/users') }}/" + user.id;
        $('#editForm').attr('action', url);

        $('#edit_nama').val(user.nama);
        $('#edit_nik').val(user.nik);
        $('#edit_username').val(user.username);
        $('#edit_jabatan_id').val(user.jabatan_id);
        $('#edit_lokasi_id').val(user.lokasi_id);
        $('#edit_is_active').val(user.is_active);
    });
});
</script>
@endpush
