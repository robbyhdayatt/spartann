{{-- resources/views/admin/converts/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Master Convert')

@section('content_header')
    <h1 class="m-0 text-dark">Master Convert</h1>
@stop

{{-- Tambahkan CSS kustom jika perlu --}}
@push('css')
<style>
    /* Agar tombol aksi tidak terlalu rapat */
    #table_converts .btn {
        margin-left: 2px;
        margin-right: 2px;
    }
    /* Atur lebar kolom action agar pas */
     #table_converts th:last-child, #table_converts td:last-child {
        width: 100px; /* Sesuaikan lebar sesuai kebutuhan */
        text-align: center;
    }
     /* Rata kanan untuk kolom angka */
    #table_converts th.text-right, #table_converts td.text-right {
        text-align: right;
    }
    /* Rata tengah untuk kolom # dan Action */
    #table_converts th.text-center, #table_converts td.text-center {
        text-align: center;
    }

</style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline"> {{-- Tambahkan kelas styling card --}}
                <div class="card-header">
                     <h3 class="card-title mt-1">
                        <i class="fas fa-exchange-alt mr-1"></i> Data Konversi Job/Part
                     </h3>
                     <div class="card-tools"> {{-- Pindahkan tombol ke kanan --}}
                        <button type="button" class="btn btn-sm btn-primary" id="btn-add" data-toggle="modal" data-target="#convertModal">
                            <i class="fas fa-plus mr-1"></i> Tambah Data
                        </button>
                     </div>
                </div>
                <div class="card-body">
                    @php
                    // Definisikan header dengan kelas untuk styling
                    $heads = [
                        ['label' => '#', 'width' => 3, 'class' => 'text-center'],
                        'Nama Job (Excel)',
                        'Part Code Hasil',
                        'Nama Part Hasil',
                        ['label' => 'Qty', 'width' => 5, 'class' => 'text-right'], // Rata kanan
                        ['label' => 'Harga Jual', 'width' => 15, 'class' => 'text-right'], // Rata kanan
                        ['label' => 'Actions', 'no-export' => true, 'width' => 10, 'class' => 'text-center'] // Rata tengah
                    ];

                    // Konfigurasi DataTable
                    $config = [
                        'processing' => true, // Tampilkan loading indicator bawaan DataTable
                        'serverSide' => false, // Kita masih pakai client-side dari contoh sebelumnya
                        'responsive' => true, // Aktifkan responsivitas
                        'autoWidth' => false, // Nonaktifkan auto width agar 'width' di $heads berfungsi
                        'order' => [[1, 'asc']], // Urutkan default berdasarkan Nama Job
                        'columns' => [ // Definisikan properti kolom
                            ['orderable' => false, 'searchable' => false, 'className' => 'text-center'], // Kolom #
                            null, // Nama Job
                            null, // Part Code Hasil
                            null, // Nama Part Hasil
                            ['className' => 'text-right'], // Qty
                            ['className' => 'text-right'], // Harga Jual
                            ['orderable' => false, 'searchable' => false, 'className' => 'text-center'] // Actions
                        ],
                        // 'dom' => '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>B', // Tambahkan 'B' untuk Buttons
                        // 'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
                    ];
                    @endphp

                    {{-- Gunakan Komponen AdminLTE DataTable --}}
                    <x-adminlte-datatable id="table_converts" :heads="$heads" :config="$config" theme="light" striped hoverable bordered compressed with-buttons>
                        {{-- Data diisi oleh Blade loop --}}
                        @forelse($converts as $index => $convert)
                            <tr>
                                <td>{{ $index + 1 }}</td> {{-- Nomor urut --}}
                                <td>{{ $convert->nama_job }}</td>
                                <td>{{ $convert->part_code_input }}</td>
                                <td>{{ $convert->part_name }}</td>
                                <td>{{ $convert->quantity }}</td> {{-- Qty sudah rata kanan via $config['columns'] --}}
                                <td>{{ 'Rp ' . number_format($convert->harga_jual, 0, ',', '.') }}</td> {{-- Format Harga Jual, sudah rata kanan via $config['columns'] --}}
                                <td> {{-- Aksi sudah rata tengah via $config['columns'] --}}
                                    <nobr>
                                        @php
                                            $editUrl = route('admin.converts.editData', $convert);
                                            $deleteUrl = route('admin.converts.destroy', $convert);
                                        @endphp
                                        <button class="btn btn-xs btn-default text-primary mx-1 shadow btn-edit" title="Edit" data-id="{{$convert->id}}" data-url="{{ $editUrl }}">
                                            <i class="fa fa-lg fa-fw fa-pen"></i>
                                        </button>
                                        <button class="btn btn-xs btn-default text-danger mx-1 shadow btn-delete" title="Delete" data-id="{{$convert->id}}" data-url="{{ $deleteUrl }}">
                                            <i class="fa fa-lg fa-fw fa-trash"></i>
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

    {{-- MODAL FORM (Styling sedikit & title statis) --}}
    <x-adminlte-modal id="convertModal" title="Form Convert" size="lg" theme="primary"
        icon="fas fa-exchange-alt" v-centered static-backdrop scrollable> {{-- Hapus :title --}}

        {{-- Pesan Error Validasi di Atas Form --}}
        <div id="validation-errors" class="alert alert-danger" style="display: none;">
            <p><strong><i class="icon fas fa-ban"></i> Perhatian!</strong> Ada kesalahan input:</p>
            <ul class="mb-0"></ul>
        </div>

        <form id="convertForm" name="convertForm">
            {{-- Konten form --}}
            @include('admin.converts._form', ['convert' => null])
        </form>

        {{-- Footer Modal --}}
        <x-slot name="footerSlot">
            {{-- Tombol Simpan dengan loading state --}}
            <x-adminlte-button class="btn-flat" theme="primary" label="Simpan" id="btn-save" icon="fas fa-save"/>
            {{-- Tombol Batal --}}
            <x-adminlte-button class="btn-flat" theme="default" label="Batal" data-dismiss="modal" icon="fas fa-times"/>
        </x-slot>
    </x-adminlte-modal>
@stop

@push('js')
{{-- SweetAlert2 --}}
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- Loading Overlay --}}
<script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>

{{-- Script AJAX (sama seperti sebelumnya) --}}
<script>
$(document).ready(function() {
    // Setup CSRF Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // --- TOMBOL TAMBAH DATA ---
    $('#btn-add').click(function () {
        $('#btn-save').html('<i class="fas fa-save mr-1"></i> Simpan').prop('disabled', false);
        $('#convertForm').trigger("reset");
        $('#convertModal .modal-title').html("Tambah Data Convert");
        $('#formMethod').val('POST');
        $('#convertForm').attr('action', "{{ route('admin.converts.store') }}");
        $('#validation-errors').hide().find('ul').empty();
        $('#convertForm [name="quantity"]').val(1);
        $('#convertForm [name="harga_modal"]').val(0);
        $('#convertForm [name="harga_jual"]').val(0);
        $('#convertForm .is-invalid').removeClass('is-invalid');
        $('#convertForm .invalid-feedback').remove();
    });

    // --- TOMBOL EDIT DATA ---
    $('body').on('click', '.btn-edit', function () {
        var convertId = $(this).data('id');
        var editUrl = $(this).data('url');

        $('#btn-save').html('<i class="fas fa-save mr-1"></i> Simpan').prop('disabled', false);
        $('#convertForm').trigger("reset");
        $('#validation-errors').hide().find('ul').empty();
        $('#convertForm .is-invalid').removeClass('is-invalid');
        $('#convertForm .invalid-feedback').remove();
        $('#convertModal .modal-body').LoadingOverlay("show");

        $.get(editUrl, function (data) {
            $('#convertModal .modal-title').html("Edit Data Convert");
            $('#btn-save').val("edit-convert");
            $('#formMethod').val('PUT');

            // Isi form
            $('#convertForm [name="original_part_code"]').val(data.original_part_code);
            $('#convertForm [name="nama_job"]').val(data.nama_job);
            $('#convertForm [name="part_name"]').val(data.part_name);
            $('#convertForm [name="merk"]').val(data.merk);
            $('#convertForm [name="part_code_input"]').val(data.part_code_input);
            $('#convertForm [name="quantity"]').val(data.quantity);
            $('#convertForm [name="harga_modal"]').val(parseFloat(data.harga_modal));
            $('#convertForm [name="harga_jual"]').val(parseFloat(data.harga_jual));
            $('#convertForm [name="keterangan"]').val(data.keterangan);

            // Set action URL
            var updateUrl = "{{ route('admin.converts.update', ':id') }}".replace(':id', convertId);
            $('#convertForm').attr('action', updateUrl);

            $('#convertModal .modal-body').LoadingOverlay("hide");
            $('#convertModal').modal('show');
        }).fail(function() {
             $('#convertModal .modal-body').LoadingOverlay("hide");
             Swal.fire('Error', 'Gagal mengambil data untuk diedit.', 'error');
        });
    });

    // --- TOMBOL SIMPAN (Action Create/Update) ---
    $('#btn-save').click(function (e) {
        e.preventDefault();
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...').prop('disabled', true);
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
                $('#convertForm').trigger("reset");
                $('#convertModal').modal('hide');
                $('#btn-save').html('<i class="fas fa-save mr-1"></i> Simpan').prop('disabled', false);

                 Swal.fire({
                    icon: 'success', title: 'Berhasil!', text: data.success, timer: 1500, showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            },
            error: function (data) {
                console.log('Error:', data);
                $('#btn-save').html('<i class="fas fa-save mr-1"></i> Simpan').prop('disabled', false);

                 if (data.status === 422) {
                    var errors = data.responseJSON.errors;
                    var errorList = $('#validation-errors ul');
                    $.each(errors, function (key, value) {
                        errorList.append('<li>' + value[0] + '</li>');
                        var inputField = $('#convertForm [name="' + key + '"]');
                        inputField.addClass('is-invalid');
                         var inputGroup = inputField.closest('.input-group, .form-group');
                         // Hapus pesan error lama sebelum menambahkan yang baru
                         inputGroup.find('.invalid-feedback').remove();
                         if (inputGroup.length) {
                             inputGroup.append('<span class="invalid-feedback d-block" role="alert"><strong>' + value[0] + '</strong></span>');
                         }
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
    $('body').on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var deleteForm = $('#delete-form-' + id);

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

    // --- Event Listener Modal Close ---
    $('#convertModal').on('hidden.bs.modal', function (e) {
        $('#convertForm').trigger("reset");
        $('#validation-errors').hide().find('ul').empty();
        $('#formMethod').val('POST');
        $('#convertForm').attr('action', "{{ route('admin.converts.store') }}");
        $('#convertForm .is-invalid').removeClass('is-invalid');
        $('#convertForm .invalid-feedback').remove();
    });

    // --- Tampilkan Pesan Session ---
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