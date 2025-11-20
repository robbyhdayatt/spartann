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
                        <th style="width: 5%">#</th>
                        <th>Lokasi (Gudang / Dealer)</th>
                        <th>Kode Rak</th>
                        <th>Nama Rak</th>
                        <th>Tipe Rak</th>
                        <th>Status</th>
                        <th style="width: 15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($raks as $rak)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        {{-- PERBAIKAN: Menggunakan relasi 'lokasi' dan kolom 'nama_lokasi' --}}
                        <td>{{ $rak->lokasi->nama_lokasi ?? 'Lokasi Terhapus' }}</td>
                        <td>{{ $rak->kode_rak }}</td>
                        <td>{{ $rak->nama_rak }}</td>
                        <td>
                            @if($rak->tipe_rak == 'KARANTINA')
                                <span class="badge badge-danger">KARANTINA</span>
                            @elseif($rak->tipe_rak == 'DISPLAY')
                                <span class="badge badge-primary">DISPLAY</span>
                            @else
                                <span class="badge badge-info">PENYIMPANAN</span>
                            @endif
                        </td>
                        <td>
                            @if($rak->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-secondary">Non-Aktif</span>
                            @endif
                        </td>
                        <td>
                            {{-- PERBAIKAN: Data attributes disesuaikan dengan kolom baru --}}
                            <button class="btn btn-warning btn-xs edit-btn"
                                    data-id="{{ $rak->id }}"
                                    data-lokasi_id="{{ $rak->lokasi_id }}"
                                    data-kode_rak="{{ $rak->kode_rak }}"
                                    data-nama_rak="{{ $rak->nama_rak }}"
                                    data-tipe_rak="{{ $rak->tipe_rak }}"
                                    data-is_active="{{ $rak->is_active }}"
                                    data-toggle="modal"
                                    data-target="#editModal">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('admin.raks.destroy', $rak->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus rak ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i> Hapus</button>
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
                            {{-- PERBAIKAN: Name menjadi lokasi_id --}}
                            <select class="form-control select2" name="lokasi_id" required style="width: 100%;">
                                <option value="" disabled selected>Pilih Lokasi</option>
                                @foreach($lokasi as $item)
                                    {{-- PERBAIKAN: Menggunakan nama_lokasi dan kode_lokasi --}}
                                    <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{$item->kode_lokasi}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipe Rak</label>
                            <select class="form-control" name="tipe_rak" required>
                                <option value="PENYIMPANAN">Penyimpanan (Gudang)</option>
                                <option value="DISPLAY">Display (Toko)</option>
                                <option value="KARANTINA">Karantina (Rusak/Retur)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Kode Rak</label><input type="text" class="form-control" name="kode_rak" placeholder="Contoh: RAK-A-01" required></div>
                        <div class="form-group"><label>Nama Rak</label><input type="text" class="form-control" name="nama_rak" placeholder="Contoh: Rak Ban Depan" required></div>
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
                            {{-- PERBAIKAN: ID dan Name menjadi lokasi_id --}}
                            <select class="form-control select2" id="edit_lokasi_id" name="lokasi_id" required style="width: 100%;">
                                @foreach($lokasi as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_lokasi }} ({{$item->kode_lokasi}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipe Rak</label>
                            <select class="form-control" name="tipe_rak" id="edit_tipe_rak" required>
                                <option value="PENYIMPANAN">Penyimpanan (Gudang)</option>
                                <option value="DISPLAY">Display (Toko)</option>
                                <option value="KARANTINA">Karantina (Rusak/Retur)</option>
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
    /* Penyesuaian tinggi Select2 agar sama dengan input form Bootstrap */
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px) !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; padding-left: .75rem !important; padding-top: .375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px) !important; }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        // Inisialisasi Select2 pada kedua modal
        $('#createModal .select2').select2({ dropdownParent: $('#createModal') });
        $('#editModal .select2').select2({ dropdownParent: $('#editModal') });

        $('#raks-table').DataTable({ "responsive": true, "autoWidth": false });

        // Logika Modal Edit
        $('.edit-btn').on('click', function() {
            var id = $(this).data('id');
            var url = "{{ url('admin/raks') }}/" + id;
            $('#editForm').attr('action', url);

            // Isi value form dari data attributes
            $('#edit_lokasi_id').val($(this).data('lokasi_id')).trigger('change'); // Trigger change untuk update Select2
            $('#edit_tipe_rak').val($(this).data('tipe_rak'));
            $('#edit_kode_rak').val($(this).data('kode_rak'));
            $('#edit_nama_rak').val($(this).data('nama_rak'));
            $('#edit_is_active').val($(this).data('is_active'));
        });
    });
</script>
@stop
