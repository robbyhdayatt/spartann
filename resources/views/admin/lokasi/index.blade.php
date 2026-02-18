@extends('adminlte::page')

@section('title', 'Manajemen Lokasi')

@section('plugins.Datatables', true)

@section('content_header')
    <h1>Manajemen Gudang & Dealer</h1>
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
            <table id="lokasi-table" class="table table-bordered table-striped table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipe</th>
                        <th>Kode</th>
                        <th>Singkatan</th>
                        <th>Nama Lokasi</th>
                        <th>Koordinator</th>
                        <th>GM</th>
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
                            @elseif($item->tipe == 'GUDANG')
                                <span class="badge badge-info">GUDANG</span>
                            @else
                            
                                <span class="badge badge-secondary">DEALER</span>
                            @endif
                        </td>
                        <td>{{ $item->kode_lokasi }}</td>
                        <td>{{ $item->singkatan }}</td>
                        <td>{{ $item->nama_lokasi }}</td>
                        <td>{{ $item->koadmin ?? '-' }}</td>
                        <td>{{ $item->gm ?? '-' }}</td>
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
                                    data-kode_lokasi="{{ $item->kode_lokasi }}"
                                    data-nama_lokasi="{{ $item->nama_lokasi }}"
                                    data-singkatan="{{ $item->singkatan }}"
                                    data-npwp="{{ $item->npwp }}"
                                    data-alamat="{{ $item->alamat }}"
                                    data-koadmin="{{ $item->koadmin }}"
                                    data-asd="{{ $item->asd }}"
                                    data-aom="{{ $item->aom }}"
                                    data-asm="{{ $item->asm }}"
                                    data-gm="{{ $item->gm }}"
                                    data-is_active="{{ $item->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editLokasiModal">
                                <i class="fas fa-edit"></i>
                            </button>

                            @if($item->tipe != 'PUSAT')
                            <form action="{{ route('admin.lokasi.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus lokasi ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah --}}
    <div class="modal fade" id="createLokasiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Lokasi Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form action="{{ route('admin.lokasi.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary"><i class="fas fa-info-circle"></i> Info Dasar</h6>
                                <hr>
                                <div class="form-group">
                                    <label>Tipe Lokasi</label>
                                    <select name="tipe" class="form-control" required>
                                        <option value="DEALER">DEALER (Cabang)</option>
                                        <option value="GUDANG">GUDANG (Main Dealer Part)</option>
                                        <option value="PUSAT">PUSAT (Main Dealer Office)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kode Lokasi</label>
                                    <input type="text" class="form-control" name="kode_lokasi" required>
                                </div>
                                <div class="form-group">
                                    <label>Nama Lokasi</label>
                                    <input type="text" class="form-control" name="nama_lokasi" required>
                                </div>
                                <div class="form-group">
                                    <label>Singkatan (3 Huruf)</label>
                                    <input type="text" class="form-control" name="singkatan" maxlength="10">
                                </div>
                                <div class="form-group">
                                    <label>NPWP</label>
                                    <input type="text" class="form-control" name="npwp">
                                </div>
                                <div class="form-group">
                                    <label>Alamat</label>
                                    <textarea class="form-control" name="alamat" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary"><i class="fas fa-sitemap"></i> Struktur Organisasi</h6>
                                <hr>
                                <div class="form-group">
                                    <label>Koordinator Admin (Koadmin)</label>
                                    <input type="text" class="form-control" name="koadmin" placeholder="Contoh: koadmin12">
                                </div>
                                <div class="form-group">
                                    <label>ASD</label>
                                    <input type="text" class="form-control" name="asd" placeholder="Contoh: ridwan">
                                </div>
                                <div class="form-group">
                                    <label>AOM</label>
                                    <input type="text" class="form-control" name="aom" placeholder="Contoh: chandra">
                                </div>
                                <div class="form-group">
                                    <label>ASM</label>
                                    <input type="text" class="form-control" name="asm" placeholder="Contoh: asm2">
                                </div>
                                <div class="form-group">
                                    <label>General Manager (GM)</label>
                                    <input type="text" class="form-control" name="gm" placeholder="Contoh: iwan">
                                </div>
                            </div>
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

    {{-- Modal Edit --}}
    <div class="modal fade" id="editLokasiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Lokasi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="editLokasiForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary"><i class="fas fa-info-circle"></i> Info Dasar</h6>
                                <hr>
                                <div class="form-group">
                                    <label>Tipe Lokasi</label>
                                    <select class="form-control" id="edit_tipe" name="tipe" required>
                                        <option value="DEALER">DEALER (Cabang)</option>
                                        <option value="GUDANG">GUDANG (Main Dealer Part)</option>
                                        <option value="PUSAT">PUSAT (Main Dealer Office)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kode Lokasi</label>
                                    <input type="text" class="form-control" id="edit_kode_lokasi" name="kode_lokasi" required>
                                </div>
                                <div class="form-group">
                                    <label>Nama Lokasi</label>
                                    <input type="text" class="form-control" id="edit_nama_lokasi" name="nama_lokasi" required>
                                </div>
                                <div class="form-group">
                                    <label>Singkatan</label>
                                    <input type="text" class="form-control" id="edit_singkatan" name="singkatan">
                                </div>
                                <div class="form-group">
                                    <label>NPWP</label>
                                    <input type="text" class="form-control" id="edit_npwp" name="npwp">
                                </div>
                                <div class="form-group">
                                    <label>Alamat</label>
                                    <textarea class="form-control" id="edit_alamat" name="alamat" rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" id="edit_is_active" name="is_active">
                                        <option value="1">Aktif</option>
                                        <option value="0">Non-Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary"><i class="fas fa-sitemap"></i> Struktur Organisasi</h6>
                                <hr>
                                <div class="form-group">
                                    <label>Koordinator Admin (Koadmin)</label>
                                    <input type="text" class="form-control" id="edit_koadmin" name="koadmin">
                                </div>
                                <div class="form-group">
                                    <label>ASD</label>
                                    <input type="text" class="form-control" id="edit_asd" name="asd">
                                </div>
                                <div class="form-group">
                                    <label>AOM</label>
                                    <input type="text" class="form-control" id="edit_aom" name="aom">
                                </div>
                                <div class="form-group">
                                    <label>ASM</label>
                                    <input type="text" class="form-control" id="edit_asm" name="asm">
                                </div>
                                <div class="form-group">
                                    <label>General Manager (GM)</label>
                                    <input type="text" class="form-control" id="edit_gm" name="gm">
                                </div>
                            </div>
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
        var table = $('#lokasi-table').DataTable({ 
            "responsive": true,
            "pageLength": 25 // Biar lebih banyak data terlihat
        });

        $('#lokasi-table').on('click', '.edit-btn', function() {
            var id = $(this).data('id');
            var url = "{{ url('admin/lokasi') }}/" + id;
            $('#editLokasiForm').attr('action', url);

            $('#edit_tipe').val($(this).data('tipe'));
            $('#edit_kode_lokasi').val($(this).data('kode_lokasi'));
            $('#edit_nama_lokasi').val($(this).data('nama_lokasi'));
            $('#edit_singkatan').val($(this).data('singkatan'));
            $('#edit_npwp').val($(this).data('npwp'));
            $('#edit_alamat').val($(this).data('alamat'));
            
            // Field Struktur
            $('#edit_koadmin').val($(this).data('koadmin'));
            $('#edit_asd').val($(this).data('asd'));
            $('#edit_aom').val($(this).data('aom'));
            $('#edit_asm').val($(this).data('asm'));
            $('#edit_gm').val($(this).data('gm'));

            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@stop