@extends('adminlte::page')

@section('title', 'Master Jabatan')
@section('plugins.Datatables', true)

@section('content_header')
    <h1>Master Jabatan</h1>
@stop

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Daftar Jabatan</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalJabatan" id="btn-add">
                <i class="fas fa-plus"></i> Tambah Jabatan
            </button>
        </div>
    </div>
    <div class="card-body">
        
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>{{ session('success') }}</x-adminlte-alert>
        @endif
        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <table id="table-jabatan" class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th style="width: 5%">No</th>
                    <th>Nama Jabatan</th>
                    <th>Singkatan</th>
                    <th>Status</th>
                    <th style="width: 15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($jabatans as $jabatan)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $jabatan->nama_jabatan }}</td>
                    <td><span class="badge badge-info">{{ $jabatan->singkatan }}</span></td>
                    <td>
                        @if($jabatan->is_active)
                            <span class="badge badge-success">Aktif</span>
                        @else
                            <span class="badge badge-secondary">Non-Aktif</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-warning btn-xs btn-edit" 
                                data-id="{{ $jabatan->id }}" 
                                data-url="{{ route('admin.jabatans.show', $jabatan->id) }}"
                                data-update="{{ route('admin.jabatans.update', $jabatan->id) }}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        
                        <form action="{{ route('admin.jabatans.destroy', $jabatan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus jabatan ini?');">
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

{{-- Modal Create/Edit --}}
<div class="modal fade" id="modalJabatan" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="form-jabatan" action="{{ route('admin.jabatans.store') }}" method="POST">
            @csrf
            <div id="method-spoof"></div> {{-- Tempat naruh @method('PUT') via JS --}}
            
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title" id="modal-title">Tambah Jabatan Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Jabatan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_jabatan" id="nama_jabatan" class="form-control" required placeholder="Contoh: Admin Gudang">
                    </div>
                    <div class="form-group">
                        <label>Singkatan / Kode <span class="text-danger">*</span></label>
                        <input type="text" name="singkatan" id="singkatan" class="form-control" required placeholder="Contoh: AG" maxlength="10">
                        <small class="text-muted">Maksimal 10 karakter. Akan otomatis menjadi huruf besar.</small>
                    </div>
                    
                    <div class="form-group" id="status-group" style="display: none;">
                        <label>Status</label>
                        <select name="is_active" id="is_active" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#table-jabatan').DataTable({
        "responsive": true,
        "autoWidth": false,
    });

    // Reset Form saat tombol Tambah diklik
    $('#btn-add').click(function() {
        $('#modal-title').text('Tambah Jabatan Baru');
        $('#form-jabatan').attr('action', "{{ route('admin.jabatans.store') }}");
        $('#method-spoof').html(''); // Hapus method PUT
        $('#nama_jabatan').val('');
        $('#singkatan').val('');
        $('#status-group').hide(); // Sembunyikan status saat create (default aktif)
    });

    // Isi Form saat tombol Edit diklik
    $('.btn-edit').click(function() {
        let url = $(this).data('url');
        let updateUrl = $(this).data('update');

        $('#modal-title').text('Edit Jabatan');
        $('#form-jabatan').attr('action', updateUrl);
        $('#method-spoof').html('<input type="hidden" name="_method" value="PUT">'); // Tambah method PUT
        $('#status-group').show();

        $.get(url, function(data) {
            $('#nama_jabatan').val(data.nama_jabatan);
            $('#singkatan').val(data.singkatan);
            $('#is_active').val(data.is_active);
            $('#modalJabatan').modal('show');
        });
    });
});
</script>
@stop