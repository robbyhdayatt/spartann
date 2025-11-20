@extends('adminlte::page')

@section('title', 'Master Convert')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)
@section('plugins.Sweetalert2', true)

@section('content_header')
    <h1>Master Convert (Mapping Service)</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Konversi Job ke Part</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createModal">
                    <i class="fas fa-plus"></i> Tambah Data
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="table-converts" class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th>Nama Job (Excel)</th>
                        <th>Barang (System)</th>
                        <th style="width: 10%;">Qty</th>
                        <th>Keterangan</th>
                        <th style="width: 15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($converts as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="font-weight-bold">{{ $item->nama_job }}</td>
                        <td>
                            @if($item->part_name)
                                {{ $item->part_name }} <br>
                                <small class="text-muted">{{ $item->part_code }}</small>
                            @else
                                <span class="text-danger">Part Tidak Ditemukan ({{ $item->part_code }})</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td>{{ $item->keterangan ?? '-' }}</td>
                        <td>
                            <button class="btn btn-warning btn-xs btn-edit"
                                    data-id="{{ $item->id }}"
                                    data-url="{{ route('admin.converts.editData', $item->id) }}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('admin.converts.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Create --}}
    <div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Konversi Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="createForm" action="{{ route('admin.converts.store') }}" method="POST">
                    @csrf {{-- TOKEN CSRF WAJIB ADA DI SINI --}}
                    <div class="modal-body">
                        @include('admin.converts._form', ['idPrefix' => 'create', 'convert' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Konversi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editForm" method="POST">
                    @csrf {{-- TOKEN CSRF WAJIB ADA DI SINI --}}
                    @method('PUT') {{-- METHOD PUT WAJIB ADA DI SINI --}}

                    <div class="modal-body">
                        @include('admin.converts._form', ['idPrefix' => 'edit', 'convert' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Perbarui</button>
                    </div>
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
    // Setup CSRF Token untuk semua request AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // 1. Init DataTable
    $('#table-converts').DataTable({
        "responsive": true,
        "autoWidth": false,
        "ordering": true
    });

    // 2. Init Select2
    $('#create_part_code').select2({ dropdownParent: $('#createModal') });
    $('#edit_part_code').select2({ dropdownParent: $('#editModal') });

    // 3. Handle Create
    $('#createForm').on('submit', function(e){
        e.preventDefault();
        let form = $(this);

        // Bersihkan Error
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.error-text').text('');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                $('#createModal').modal('hide');
                Swal.fire('Berhasil', response.success, 'success').then(() => location.reload());
            },
            error: function(xhr) {
                handleAjaxError(xhr, 'create_');
            }
        });
    });

    // 4. Handle Edit Button
    $('.btn-edit').on('click', function() {
        let id = $(this).data('id');
        let url = $(this).data('url');

        $('#editForm')[0].reset();
        $('#editForm .is-invalid').removeClass('is-invalid');
        $('#editForm .error-text').text('');

        $.get(url, function(data) {
            $('#edit_nama_job').val(data.nama_job);
            $('#edit_quantity').val(data.quantity);
            $('#edit_keterangan').val(data.keterangan);

            if ($('#edit_part_code').find("option[value='" + data.part_code + "']").length) {
                $('#edit_part_code').val(data.part_code).trigger('change');
            }

            let updateUrl = "{{ url('admin/converts') }}/" + id;
            $('#editForm').attr('action', updateUrl);
            $('#editModal').modal('show');
        }).fail(function() {
            alert('Gagal mengambil data.');
        });
    });

    // 5. Handle Update
    $('#editForm').on('submit', function(e){
        e.preventDefault();
        let form = $(this);

        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.error-text').text('');

        $.ajax({
            url: form.attr('action'),
            method: 'PUT', // jQuery akan otomatis mengubah ini menjadi POST dengan _method=PUT
            data: form.serialize(),
            success: function(response) {
                $('#editModal').modal('hide');
                Swal.fire('Berhasil', response.success, 'success').then(() => location.reload());
            },
            error: function(xhr) {
                handleAjaxError(xhr, 'edit_');
            }
        });
    });

    // Helper Error Handler
    function handleAjaxError(xhr, prefix) {
        if(xhr.status === 422) {
            let errors = xhr.responseJSON.errors;
            $.each(errors, function(key, val) {
                let input = $('#' + prefix + key);
                input.addClass('is-invalid');
                input.closest('.form-group').find('.error-text').text(val[0]);
            });
        } else if (xhr.status === 419) {
             Swal.fire('Sesi Habis', 'Silakan refresh halaman dan login kembali.', 'warning');
        } else {
            let msg = xhr.responseJSON ? (xhr.responseJSON.error || xhr.responseJSON.message) : 'Terjadi kesalahan server (' + xhr.status + ')';
            Swal.fire('Error', msg, 'error');
            console.error(xhr.responseText);
        }
    }
});
</script>
@stop
