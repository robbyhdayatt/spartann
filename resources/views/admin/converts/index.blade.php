@extends('adminlte::page')

@section('title', 'Master Convert')
@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1 class="m-0 text-dark">Master Convert</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mt-1">
                        <i class="fas fa-exchange-alt mr-1"></i> Data Konversi (Job/Part)
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-primary" id="btn-add" data-toggle="modal" data-target="#convertModal">
                            <i class="fas fa-plus mr-1"></i> Tambah Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Tampilkan Pesan Sukses/Error dari redirect (Destroy) --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif
                     @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @php
                    $heads = [
                        ['label' => '#', 'width' => 3, 'class' => 'text-center'],
                        'Nama Job (Excel)',
                        'Nama Part',
                        'Kode Part',
                        ['label' => 'Qty', 'width' => 5, 'class' => 'text-right'],
                        ['label' => 'Harga Jual', 'width' => 15, 'class' => 'text-right'],
                        ['label' => 'Aksi', 'no-export' => true, 'width' => 10, 'class' => 'text-center']
                    ];
                    $config = [
                        'order' => [[1, 'asc']],
                        'columns' => [
                            ['orderable' => false, 'searchable' => false, 'className' => 'text-center'],
                            null, null, null,
                            ['className' => 'text-right'],
                            ['className' => 'text-right'],
                            ['orderable' => false, 'searchable' => false, 'className' => 'text-center']
                        ],
                    ];
                    @endphp

                    {{-- Tampilkan DataTable --}}
                    <x-adminlte-datatable id="table_converts" :heads="$heads" :config="$config" theme="light" striped hoverable bordered compressed with-buttons>
                        @forelse($converts as $index => $convert)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $convert->nama_job }}</td>
                                <td>{{ $convert->part_name }}</td>
                                <td>{{ $convert->part_code }}</td>
                                <td>{{ $convert->quantity }}</td>
                                <td>@rupiah($convert->harga_jual)</td>
                                <td>
                                    <nobr>
                                        @php
                                            $showUrl = route('admin.converts.editData', $convert->id);
                                            $deleteUrl = route('admin.converts.destroy', $convert->id);
                                        @endphp

                                        {{-- Tombol Edit TIDAK menggunakan data-toggle="modal" --}}
                                        <button class="btn btn-xs btn-warning btn-edit" title="Edit"
                                                data-id="{{$convert->id}}"
                                                data-url="{{ $showUrl }}">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <button class="btn btn-xs btn-danger btn-delete" title="Delete"
                                                data-id="{{$convert->id}}"
                                                data-url="{{ $deleteUrl }}">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        <form id="delete-form-{{$convert->id}}" action="{{ $deleteUrl }}" method="POST" style="display: none;">
                                            @csrf @method('DELETE')
                                        </form>
                                    </nobr>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($heads) }}" class="text-center">Tidak ada data ditemukan.</td>
                            </tr>
                        @endforelse
                    </x-adminlte-datatable>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <div class="modal fade" id="convertModal" tabindex="-1" role="dialog" aria-labelledby="convertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form id="convertForm" name="convertForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="convertModalLabel">Form Convert</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="validation-errors" class="alert alert-danger" style="display: none;">
                            <p><strong><i class="icon fas fa-ban"></i> Perhatian!</strong> Ada kesalahan input:</p>
                            <ul class="mb-0"></ul>
                        </div>
                        @include('admin.converts._form', [
                            'convert' => null,
                            'barangs' => $barangs,
                            'idPrefix' => 'modal'
                        ])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn-save">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@push('css')
<style>
    .select2-container--bootstrap4 .select2-dropdown {
        z-index: 1060;
    }
    .invalid-feedback.d-block {
        display: block !important;
    }
</style>
@endpush

@push('js')
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>

<script>
$(document).ready(function() {
    // Setup CSRF Token
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Inisialisasi Select2 di dalam modal
    $('.select2-modal').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#convertModal .modal-body')
    });

    // Fungsi untuk membersihkan form modal
    function resetModalForm(formElement = '#convertForm') {
        let form = $(formElement);

        form.trigger("reset");

        // Kosongkan nilai input secara manual
        $('#modal_nama_job').val('');
        $('#modal_keterangan').val('');
        $('#modal_quantity').val(1); // Set Qty kembali ke 1

        // Reset Select2
        $('#modal_part_code').val(null).trigger('change');

        // Hapus error validasi
        $('#validation-errors').hide().find('ul').empty();
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').remove();
    }

    // --- TOMBOL TAMBAH DATA ---
    $('#btn-add').click(function () {
        // ++ PERBAIKAN: Selalu panggil resetModalForm saat tombol Tambah diklik ++
        resetModalForm('#convertForm');

        // Atur state modal ke 'Create'
        $('#convertForm').attr('action', "{{ route('admin.converts.store') }}");
        $('#formMethod').val('POST');
        $('#convertModal .modal-title').html("Tambah Data Convert");
        // data-toggle="modal" di tombol sudah menangani 'show'
    });

    // --- TOMBOL EDIT DATA ---
    // Gunakan event delegation pada parent yang statis (tabel)
    $('#table_converts').on('click', '.btn-edit', function () {
        var convertId = $(this).data('id');
        var editUrl = $(this).data('url');

        // Atur state modal ke 'Edit' SEBELUM mengambil data
        $('#convertForm').attr('action', "{{ route('admin.converts.update', ':id') }}".replace(':id', convertId));
        $('#formMethod').val('PUT');
        $('#convertModal .modal-title').html("Edit Data Convert");

        $('#convertModal .modal-body').LoadingOverlay("show");

        $.get(editUrl, function (data) {
            // Isi form
            $('#modal_nama_job').val(data.nama_job);
            $('#modal_quantity').val(data.quantity);
            $('#modal_keterangan').val(data.keterangan);
            $('#modal_part_code').val(data.part_code).trigger('change');

            $('#convertModal .modal-body').LoadingOverlay("hide");
            $('#convertModal').modal('show'); // Buka modal secara manual
        }).fail(function() {
             $('#convertModal .modal-body').LoadingOverlay("hide");
             Swal.fire('Error', 'Gagal mengambil data untuk diedit.', 'error');
        });
    });

    // --- Event saat Modal DITUTUP ('hidden.bs.modal') ---
    $('#convertModal').on('hidden.bs.modal', function (e) {
        // Selalu reset form saat modal ditutup (baik sukses, batal, atau error)
        resetModalForm('#convertForm');
        // Kembalikan tombol save ke state semula
        $('#btn-save').html('Simpan').prop('disabled', false);
    });

     // --- Logika Validasi Error (dari server, saat reload) ---
     @if($errors->any())
        @if(session('edit_form_id'))
            // Error validasi dari UPDATE
            let failedId = {{ session('edit_form_id') }};
            let updateUrl = "{{ route('admin.converts.update', ':id') }}".replace(':id', failedId);
            $('#convertForm').attr('action', updateUrl);
            $('#formMethod').val('PUT');
            $('#convertModal .modal-title').html("Edit Data Convert (Error)");
            $('#convertModal').modal('show');
        @else
            // Error validasi dari CREATE
            $('#convertModal .modal-title').html("Tambah Data Convert (Error)");
            $('#convertModal').modal('show');
        @endif
    @endif

    // --- TOMBOL SIMPAN (Action Create/Update) ---
    $('#btn-save').click(function (e) {
        e.preventDefault();
        var $this = $(this);
        $this.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...').prop('disabled', true);

        $('#validation-errors').hide().find('ul').empty();
        $('#convertForm .is-invalid').removeClass('is-invalid');
        $('#convertForm .invalid-feedback').remove();

        var formData = $('#convertForm').serialize();
        var method = $('#formMethod').val();
        var url = $('#convertForm').attr('action');

        $.ajax({
            data: formData,
            url: url,
            type: method,
            dataType: 'json',
            success: function (data) {
                $('#convertModal').modal('hide');
                Swal.fire({
                    icon: 'success', title: 'Berhasil!', text: data.success, timer: 1500, showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            },
            error: function (data) {
                console.log('Error:', data);
                $this.html('Simpan').prop('disabled', false);

                if (data.status === 422) {
                    var errors = data.responseJSON.errors;
                    var errorList = $('#validation-errors ul');
                    $.each(errors, function (key, value) {
                        errorList.append('<li>' + value[0] + '</li>');
                        var inputField = $('#convertForm [name="' + key + '"]');
                        inputField.addClass('is-invalid');

                        var inputGroup = inputField.closest('.form-group');
                        inputGroup.find('.invalid-feedback').remove();
                        inputGroup.append('<span class="invalid-feedback d-block" role="alert"><strong>' + value[0] + '</strong></span>');
                    });
                    $('#validation-errors').show();
                    $('#convertModal .modal-body').animate({ scrollTop: 0 }, 'slow');
                } else {
                    var errorMsg = data.responseJSON && data.responseJSON.error ? data.responseJSON.error : 'Terjadi kesalahan pada server.';
                    Swal.fire('Error!', errorMsg, 'error');
                }
            }
        });
    });

    // --- TOMBOL DELETE ---
    $('#table_converts').on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var deleteForm = $('#delete-form-' + id); // <-- Form di dalam <td>

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus Saja!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteForm.submit();
            }
        });
    });

    // --- Tampilkan Pesan Session (dari redirect Delete) ---
    @if(session('success'))
        Swal.fire({
            icon: 'success', title: 'Berhasil!', text: '{{ session('success') }}', timer: 3000, showConfirmButton: false
        });
    @endif
    @if(session('error'))
        Swal.fire({
            icon: 'error', title: 'Gagal!', text: '{{ session('error') }}',
        });
    @endif
});
</script>
@endpush
