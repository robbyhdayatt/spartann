@extends('adminlte::page')

@section('title', 'Manajemen Lokasi')

@section('plugins.Datatables', true)

@section('content_header')
    <h1>Manajemen Lokasi (Gudang & Dealer)</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Lokasi</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createLokasiModal">
                    <i class="fas fa-plus"></i> Tambah Lokasi
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="lokasi-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipe</th>
                        <th>Kode</th>
                        <th>Nama Lokasi</th>
                        <th>Alamat</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lokasi as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            @if($item->tipe == 'PUSAT')
                                <span class="badge badge-primary">PUSAT</span>
                            @else
                                <span class="badge badge-secondary">DEALER</span>
                            @endif
                        </td>
                        <td>{{ $item->kode_gudang }}</td>
                        <td>{{ $item->nama_gudang }}</td>
                        <td>{{ $item->alamat }}</td>
                        <td>
                            @if($item->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Non-Aktif</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $item->id }}"
                                    data-tipe="{{ $item->tipe }}"
                                    data-kode_gudang="{{ $item->kode_gudang }}"
                                    data-nama_gudang="{{ $item->nama_gudang }}"
                                    data-alamat="{{ $item->alamat }}"
                                    data-is_active="{{ $item->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editLokasiModal">
                                Edit
                            </button>
                            @if($item->tipe != 'PUSAT')
                            <form action="{{ route('admin.lokasi.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus lokasi ini?');">
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

    <div class="modal fade" id="createLokasiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Lokasi Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form action="{{ route('admin.lokasi.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Tipe Lokasi</label>
                            <select class="form-control" name="tipe" required>
                                <option value="DEALER">Dealer</option>
                                <option value="PUSAT">Gudang Pusat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kode Lokasi</label>
                            <input type="text" class="form-control" name="kode_gudang" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Lokasi</label>
                            <input type="text" class="form-control" name="nama_gudang" required>
                        </div>
                         <div class="form-group">
                            <label>Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editLokasiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Lokasi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="editLokasiForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Tipe Lokasi</label>
                            <select class="form-control" id="edit_tipe" name="tipe" required>
                                <option value="DEALER">Dealer</option>
                                <option value="PUSAT">Gudang Pusat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kode Lokasi</label>
                            <input type="text" class="form-control" id="edit_kode_gudang" name="kode_gudang" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Lokasi</label>
                            <input type="text" class="form-control" id="edit_nama_gudang" name="nama_gudang" required>
                        </div>
                         <div class="form-group">
                            <label>Alamat</label>
                            <textarea class="form-control" id="edit_alamat" name="alamat" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="edit_is_active" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#lokasi-table').DataTable({ "responsive": true });

        $('.edit-btn').on('click', function() {
            var id = $(this).data('id');
            var url = "{{ url('admin/lokasi') }}/" + id;
            $('#editLokasiForm').attr('action', url);

            $('#edit_tipe').val($(this).data('tipe'));
            $('#edit_kode_gudang').val($(this).data('kode_gudang'));
            $('#edit_nama_gudang').val($(this).data('nama_gudang'));
            $('#edit_alamat').val($(this).data('alamat'));
            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@stop
