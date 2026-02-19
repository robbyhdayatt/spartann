@extends('adminlte::page')

@section('title', 'Manajemen Rak')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Manajemen Rak</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-pallet mr-2"></i> Daftar Rak Penyimpanan
            </h3>
            <div class="card-tools">
                {{-- [MODIFIKASI] Hanya Super Admin (manage-raks) yang bisa lihat tombol Tambah --}}
                @can('manage-raks')
                <button type="button" class="btn btn-primary btn-sm elevation-2" data-toggle="modal" data-target="#createModal">
                    <i class="fas fa-plus-circle mr-1"></i> Tambah Rak
                </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <table id="raks-table" class="table table-bordered table-striped table-hover table-sm">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 5%" class="text-center">No</th>
                        <th>Lokasi</th>
                        <th>Kode Rak (Zona-Rak-Level-Bin)</th>
                        <th class="text-center">Tipe</th>
                        <th class="text-center">Status</th>
                        {{-- [MODIFIKASI] Kolom Aksi hanya muncul jika user punya akses manage --}}
                        @can('manage-raks')
                        <th style="width: 15%" class="text-center">Aksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($raks as $rak)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $rak->lokasi->nama_lokasi ?? 'Lokasi ?' }}</td>
                        <td>
                            <span class="badge badge-light border" style="font-size: 0.95rem;">
                                <i class="fas fa-barcode mr-1 text-muted"></i> {{ $rak->kode_rak }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($rak->tipe_rak == 'KARANTINA')
                                <span class="badge badge-danger px-3 py-1">KARANTINA</span>
                            @else
                                <span class="badge badge-success px-3 py-1">PENYIMPANAN</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($rak->is_active)
                                <span class="badge badge-primary">Aktif</span>
                            @else
                                <span class="badge badge-secondary">Non-Aktif</span>
                            @endif
                        </td>
                        
                        {{-- [MODIFIKASI] Tombol Aksi dibungkus Gate --}}
                        @can('manage-raks')
                        <td class="text-center">
                            <button class="btn btn-warning btn-xs edit-btn shadow-sm"
                                    title="Edit Rak"
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
                            <form action="{{ route('admin.raks.destroy', $rak->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus rak {{ $rak->kode_rak }}? Data tidak bisa dikembalikan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs shadow-sm" title="Hapus Rak"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create Modal --}}
    @can('manage-raks')
    <div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-labelledby="createModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="createModalLabel"><i class="fas fa-plus-square mr-2"></i>Tambah Rak Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.raks.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle mr-1"></i> Format Kode Rak otomatis: <strong>ZONA-RAK-LEVEL-BIN</strong>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right">Lokasi Gudang</label>
                            <div class="col-sm-9">
                                <select class="form-control select2" name="lokasi_id" required style="width: 100%;">
                                    <option value="" disabled selected>-- Pilih Lokasi --</option>
                                    @foreach($lokasi as $item)
                                        <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{$item->kode_lokasi}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="small text-muted mb-0">ZONA</label>
                                    <input type="text" class="form-control text-uppercase font-weight-bold text-center" name="zona" placeholder="A" maxlength="3" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="small text-muted mb-0">NO. RAK</label>
                                    <input type="text" class="form-control text-uppercase font-weight-bold text-center" name="nomor_rak" placeholder="R01" maxlength="5" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="small text-muted mb-0">LEVEL</label>
                                    <input type="text" class="form-control text-uppercase font-weight-bold text-center" name="level" placeholder="L1" maxlength="3" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="small text-muted mb-0">BIN</label>
                                    <input type="text" class="form-control text-uppercase font-weight-bold text-center" name="bin" placeholder="B01" maxlength="5" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row mt-3">
                            <label class="col-sm-3 col-form-label text-right">Tipe Rak</label>
                            <div class="col-sm-9">
                                <select class="form-control" name="tipe_rak" required>
                                    <option value="PENYIMPANAN" selected>Penyimpanan (Standard)</option>
                                    <option value="KARANTINA">Karantina (Barang Rusak/Retur)</option>
                                    {{-- [MODIFIKASI] Opsi DISPLAY Dihapus --}}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Rak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- Edit Modal --}}
    @can('manage-raks')
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-warning">
                    <h5 class="modal-title text-white" id="editModalLabel"><i class="fas fa-edit mr-2"></i>Edit Rak</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right">Lokasi</label>
                            <div class="col-sm-9">
                                <select class="form-control select2" id="edit_lokasi_id" name="lokasi_id" required style="width: 100%;">
                                    @foreach($lokasi as $item)
                                        <option value="{{ $item->id }}">{{ $item->nama_lokasi }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row bg-light p-3 rounded border my-3">
                            <div class="col-3 text-center">
                                <label class="small text-muted mb-1">ZONA</label>
                                <input type="text" class="form-control text-uppercase font-weight-bold text-center" id="edit_zona" name="zona" required>
                            </div>
                            <div class="col-3 text-center">
                                <label class="small text-muted mb-1">NO. RAK</label>
                                <input type="text" class="form-control text-uppercase font-weight-bold text-center" id="edit_nomor_rak" name="nomor_rak" required>
                            </div>
                            <div class="col-3 text-center">
                                <label class="small text-muted mb-1">LEVEL</label>
                                <input type="text" class="form-control text-uppercase font-weight-bold text-center" id="edit_level" name="level" required>
                            </div>
                            <div class="col-3 text-center">
                                <label class="small text-muted mb-1">BIN</label>
                                <input type="text" class="form-control text-uppercase font-weight-bold text-center" id="edit_bin" name="bin" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tipe Rak</label>
                                    <select class="form-control" name="tipe_rak" id="edit_tipe_rak" required>
                                        <option value="PENYIMPANAN">Penyimpanan</option>
                                        <option value="KARANTINA">Karantina</option>
                                        {{-- [MODIFIKASI] Opsi DISPLAY Dihapus --}}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status Aktif</label>
                                    <select class="form-control" id="edit_is_active" name="is_active">
                                        <option value="1">Aktif</option>
                                        <option value="0">Non-Aktif (Arsipkan)</option>
                                    </select>
                                    <small class="text-danger">*Hanya bisa nonaktif jika stok 0</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt mr-1"></i> Update Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
@stop

@push('js')
<script>
    $(document).ready(function() {
        $('#raks-table').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
            }
        });
        
        $('.select2').select2({
            theme: 'bootstrap4'
        });

        // Handler Edit Button
        $(document).on('click', '.edit-btn', function() {
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