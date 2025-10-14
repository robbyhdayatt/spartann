@extends('adminlte::page')

@section('title', 'Master Dealer')

@section('plugins.Datatables', true)

@section('content_header')
    <h1>Master Dealer</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Dealer</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createDealerModal">
                    <i class="fas fa-plus"></i> Tambah Dealer
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="dealers-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode</th>
                        <th>Nama Dealer</th>
                        <th>Grup</th>
                        <th>Kota</th>
                        <th>Singkatan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dealers as $dealer)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $dealer->kode_dealer }}</td>
                        <td>{{ $dealer->nama_dealer }}</td>
                        <td>{{ $dealer->grup }}</td>
                        <td>{{ $dealer->kota }}</td>
                        <td>{{ $dealer->singkatan }}</td>
                        <td>
                            @if($dealer->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Non-Aktif</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $dealer->id }}"
                                    data-kode_dealer="{{ $dealer->kode_dealer }}"
                                    data-nama_dealer="{{ $dealer->nama_dealer }}"
                                    data-grup="{{ $dealer->grup }}"
                                    data-kota="{{ $dealer->kota }}"
                                    data-singkatan="{{ $dealer->singkatan }}"
                                    data-is_active="{{ $dealer->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editDealerModal">
                                Edit
                            </button>
                            <form action="{{ route('admin.dealers.destroy', $dealer->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin?');">
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

    <div class="modal fade" id="createDealerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tambah Dealer Baru</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form action="{{ route('admin.dealers.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group"><label>Kode Dealer</label><input type="text" class="form-control" name="kode_dealer" required></div>
                        <div class="form-group"><label>Nama Dealer</label><input type="text" class="form-control" name="nama_dealer" required></div>
                        <div class="form-group"><label>Grup</label><input type="text" class="form-control" name="grup"></div>
                        <div class="form-group"><label>Kota</label><input type="text" class="form-control" name="kota"></div>
                        <div class="form-group"><label>Singkatan</label><input type="text" class="form-control" name="singkatan"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDealerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Dealer</h5><button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button></div>
                <form id="editDealerForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group"><label>Kode Dealer</label><input type="text" class="form-control" id="edit_kode_dealer" name="kode_dealer" required></div>
                        <div class="form-group"><label>Nama Dealer</label><input type="text" class="form-control" id="edit_nama_dealer" name="nama_dealer" required></div>
                        <div class="form-group"><label>Grup</label><input type="text" class="form-control" id="edit_grup" name="grup"></div>
                        <div class="form-group"><label>Kota</label><input type="text" class="form-control" id="edit_kota" name="kota"></div>
                        <div class="form-group"><label>Singkatan</label><input type="text" class="form-control" id="edit_singkatan" name="singkatan"></div>
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

@section('js')
<script>
    $(document).ready(function() {
        $('#dealers-table').DataTable({ "responsive": true });

        $('.edit-btn').on('click', function() {
            var id = $(this).data('id');
            var url = "{{ url('admin/dealers') }}/" + id;
            $('#editDealerForm').attr('action', url);

            $('#edit_kode_dealer').val($(this).data('kode_dealer'));
            $('#edit_nama_dealer').val($(this).data('nama_dealer'));
            $('#edit_grup').val($(this).data('grup'));
            $('#edit_kota').val($(this).data('kota'));
            $('#edit_singkatan').val($(this).data('singkatan'));
            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@stop
