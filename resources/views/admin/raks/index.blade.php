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
            <h3 class="card-title">Daftar Rak Penyimpanan</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createModal">
                    <i class="fas fa-plus"></i> Tambah Rak
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="raks-table" class="table table-bordered table-striped table-sm">
                <thead>
                    <tr>
                        <th style="width: 5%">No</th>
                        <th>Lokasi</th>
                        <th>Kode Rak (Zona-Rak-Level-Bin)</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th style="width: 15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($raks as $rak)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $rak->lokasi->nama_lokasi ?? 'Lokasi ?' }}</td>
                        <td>
                            <span class="badge badge-light" style="font-size: 1rem;">{{ $rak->kode_rak }}</span>
                        </td>
                        <td>
                            @if($rak->tipe_rak == 'KARANTINA')
                                <span class="badge badge-danger">KARANTINA</span>
                            @elseif($rak->tipe_rak == 'DISPLAY')
                                <span class="badge badge-info">DISPLAY</span>
                            @else
                                <span class="badge badge-success">PENYIMPANAN</span>
                            @endif
                        </td>
                        <td>{{ $rak->is_active ? 'Aktif' : 'Non-Aktif' }}</td>
                        <td>
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $rak->id }}"
                                    data-lokasi_id="{{ $rak->lokasi_id }}"
                                    data-zona="{{ $rak->zona }}"
                                    data-nomor_rak="{{ $rak->nomor_rak }}"
                                    data-level="{{ $rak->level }}"
                                    data-bin="{{ $rak->bin }}"
                                    data-tipe_rak="{{ $rak->tipe_rak }}"
                                    data-is_active="{{ $rak->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('admin.raks.destroy', $rak->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus rak ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></button>
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
                <div class="modal-header bg-primary text-white"><h5 class="modal-title">Tambah Rak Baru</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <form action="{{ route('admin.raks.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Lokasi Gudang/Dealer</label>
                            <select class="form-control select2" name="lokasi_id" required style="width: 100%;">
                                <option value="" disabled selected>Pilih Lokasi</option>
                                @foreach($lokasi as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{$item->kode_lokasi}})</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Zona</label>
                                    <input type="text" class="form-control text-uppercase" name="zona" placeholder="A" maxlength="3" required>
                                    <small class="text-muted">Ex: A</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>No. Rak</label>
                                    <input type="text" class="form-control text-uppercase" name="nomor_rak" placeholder="R01" maxlength="5" required>
                                    <small class="text-muted">Ex: R01</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Level</label>
                                    <input type="text" class="form-control text-uppercase" name="level" placeholder="L1" maxlength="3" required>
                                    <small class="text-muted">Ex: L1</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Bin</label>
                                    <input type="text" class="form-control text-uppercase" name="bin" placeholder="B01" maxlength="5" required>
                                    <small class="text-muted">Ex: B01</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tipe Rak</label>
                            <select class="form-control" name="tipe_rak" required>
                                <option value="PENYIMPANAN">Penyimpanan</option>
                                <option value="DISPLAY">Display</option>
                                <option value="KARANTINA">Karantina</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning"><h5 class="modal-title">Edit Rak</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Lokasi</label>
                            <select class="form-control select2" id="edit_lokasi_id" name="lokasi_id" required style="width: 100%;">
                                @foreach($lokasi as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_lokasi }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-3"><input type="text" class="form-control text-uppercase" id="edit_zona" name="zona" required></div>
                            <div class="col-3"><input type="text" class="form-control text-uppercase" id="edit_nomor_rak" name="nomor_rak" required></div>
                            <div class="col-3"><input type="text" class="form-control text-uppercase" id="edit_level" name="level" required></div>
                            <div class="col-3"><input type="text" class="form-control text-uppercase" id="edit_bin" name="bin" required></div>
                        </div>
                        <div class="form-group mt-3">
                            <label>Tipe & Status</label>
                            <div class="row">
                                <div class="col-6">
                                    <select class="form-control" name="tipe_rak" id="edit_tipe_rak" required>
                                        <option value="PENYIMPANAN">Penyimpanan</option>
                                        <option value="DISPLAY">Display</option>
                                        <option value="KARANTINA">Karantina</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-control" id="edit_is_active" name="is_active">
                                        <option value="1">Aktif</option>
                                        <option value="0">Non-Aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-warning">Update</button></div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
    $(document).ready(function() {
        $('#raks-table').DataTable();
        $('.select2').select2();

        $('.edit-btn').on('click', function() {
            let id = $(this).data('id');
            $('#editForm').attr('action', "{{ url('admin/raks') }}/" + id);
            $('#edit_lokasi_id').val($(this).data('lokasi_id')).trigger('change');
            $('#edit_zona').val($(this).data('zona'));
            $('#edit_nomor_rak').val($(this).data('nomor_rak'));
            $('#edit_level').val($(this).data('level'));
            $('#edit_bin').val($(this).data('bin'));
            $('#edit_tipe_rak').val($(this).data('tipe_rak'));
            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@endpush