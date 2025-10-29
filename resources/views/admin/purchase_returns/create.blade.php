@extends('adminlte::page')
@section('title', 'Buat Retur Pembelian')
@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Retur Pembelian</h1>
@stop

@section('content')
    <div class="card">
        <form action="{{ route('admin.purchase-returns.store') }}" method="POST" id="return-form">
            @csrf
            <div class="card-body">

                {{-- ++ BLOK ERROR YANG DIPERBAIKI ++ --}}
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <p><strong>Terjadi kesalahan:</strong></p>
                        <ul class="mb-0">
                            @php
                                $hasItemError = false;
                            @endphp
                            @foreach ($errors->all() as $error)
                                @if (preg_match('/^items\.\d+\..*/', $error))
                                    {{-- Jika ini adalah error item, set flag dan jangan tampilkan di list atas --}}
                                    @php $hasItemError = true; @endphp
                                @else
                                    {{-- Tampilkan error umum (bukan error item) --}}
                                    <li>{{ $error }}</li>
                                @endif
                            @endforeach
                            {{-- Jika ada error item, tampilkan pesan ringkasan --}}
                            @if ($hasItemError)
                                <li>Terdapat kesalahan pada input item retur. Silakan periksa detail di bawah tabel.</li>
                            @endif
                        </ul>
                    </div>
                @endif
                {{-- ++ AKHIR BLOK ERROR ++ --}}

                {{-- Input Header Retur --}}
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="receiving-select">Pilih Dokumen Penerimaan <span class="text-danger">*</span></label>
                        <select id="receiving-select" name="receiving_id"
                            class="form-control select2 @error('receiving_id') is-invalid @enderror" required>
                            <option value="" disabled {{ old('receiving_id') ? '' : 'selected' }}>--- Pilih Penerimaan
                                ---</option>
                            @foreach ($receivings as $receiving)
                                <option value="{{ $receiving->id }}"
                                    {{ old('receiving_id') == $receiving->id ? 'selected' : '' }}>
                                    {{ $receiving->nomor_penerimaan }} -
                                    {{ $receiving->purchaseOrder->supplier->nama_supplier ?? 'N/A' }} -
                                    {{ \Carbon\Carbon::parse($receiving->tanggal_terima)->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                        @error('receiving_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="tanggal_retur">Tanggal Retur <span class="text-danger">*</span></label>
                        <input type="date" id="tanggal_retur"
                            class="form-control @error('tanggal_retur') is-invalid @enderror" name="tanggal_retur"
                            value="{{ old('tanggal_retur', now()->format('Y-m-d')) }}" required>
                        @error('tanggal_retur')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="form-group">
                    <label for="catatan">Catatan</label>
                    <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="2"
                        placeholder="Catatan tambahan untuk retur (opsional)">{{ old('catatan') }}</textarea>
                    @error('catatan')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Tabel Item Retur --}}
                <h5 class="mt-4">Item untuk Diretur</h5>
                <p class="text-muted">Masukkan jumlah barang yang akan diretur pada kolom "Qty Diretur". Hanya item yang
                    gagal QC dan belum diretur yang akan tampil.</p>
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Part</th>
                            <th style="width: 150px" class="text-center">Qty Gagal QC <br>(Tersedia Retur)</th>
                            <th style="width: 150px" class="text-center">Qty Diretur <span class="text-danger">*</span></th>
                            <th>Alasan Retur (Opsional)</th>
                        </tr>
                    </thead>
                    <tbody id="return-items-table">
                        @if (!old('receiving_id'))
                            <tr>
                                <td colspan="4" class="text-center text-muted">Pilih dokumen penerimaan terlebih dahulu.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                @error('items')
                    <div class="text-danger mt-2">{{ $message }}</div>
                @enderror

            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" id="submit-button" disabled>Simpan Dokumen Retur</button>
                <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap4'
            });

            const validationErrors = @json($errors->toArray());
            const oldItems = @json(old('items', []));
            const hasOldReceivingId = {{ old('receiving_id') ? 'true' : 'false' }};

            const receivingSelect = $('#receiving-select');
            const tableBody = $('#return-items-table');
            const submitButton = $('#submit-button');

            function loadItems(receivingId) {
                tableBody.html(
                    '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>'
                    );
                submitButton.prop('disabled', true);

                if (!receivingId) {
                    if (!hasOldReceivingId) {
                        tableBody.html(
                            '<tr><td colspan="4" class="text-center text-muted">Pilih dokumen penerimaan terlebih dahulu.</td></tr>'
                            );
                    } else {
                        tableBody.empty();
                    }
                    return;
                }

                let urlTemplate = "{{ route('admin.api.receivings.failed-items', ['receiving' => ':id']) }}";
                let url = urlTemplate.replace(':id', receivingId);

                $.getJSON(url)
                    .done(function(data) {
                        tableBody.empty();
                        if (data && data.length > 0) {
                            let hasItemsToReturn = false;
                            data.forEach(function(item) {
                                let availableToReturn = parseInt(item.qty_gagal_qc) - parseInt(item
                                    .qty_diretur || 0);
                                if (availableToReturn > 0) {
                                    hasItemsToReturn = true;

                                    let qtyErrorKey = `items.${item.id}.qty_retur`;
                                    let alasanErrorKey = `items.${item.id}.alasan`;
                                    let qtyErrorMessage = validationErrors[qtyErrorKey] ? (Array
                                        .isArray(validationErrors[qtyErrorKey]) ? validationErrors[
                                            qtyErrorKey][0] : validationErrors[qtyErrorKey]) : '';
                                    let alasanErrorMessage = validationErrors[alasanErrorKey] ? (Array
                                        .isArray(validationErrors[alasanErrorKey]) ?
                                        validationErrors[alasanErrorKey][0] : validationErrors[
                                            alasanErrorKey]) : '';
                                    let isQtyInvalid = qtyErrorMessage !== '';
                                    let isAlasanInvalid = alasanErrorMessage !== '';

                                    let oldQtyValue = oldItems[item.id] ? oldItems[item.id][
                                        'qty_retur'] : null;
                                    let oldAlasanValue = oldItems[item.id] ? oldItems[item.id][
                                        'alasan'] : null;

                                    let defaultValueQty = oldQtyValue !== null ? oldQtyValue :
                                        availableToReturn;
                                    let defaultValueAlasan = oldAlasanValue !== null ? oldAlasanValue :
                                        (item.catatan_qc || '');

                                    let row = `
                                <tr class="item-row">
                                    <td>
                                        ${item.part.nama_part} (${item.part.kode_part})
                                        <input type="hidden" name="items[${item.id}][receiving_detail_id]" value="${item.id}">
                                    </td>
                                    <td class="text-center">
                                         <input type="number" class="form-control-plaintext text-center available-qty" value="${availableToReturn}" readonly style="background: transparent; border: none;">
                                    </td>
                                    <td>
                                        <input type="number" name="items[${item.id}][qty_retur]"
                                               class="form-control qty-retur ${isQtyInvalid ? 'is-invalid' : ''}"
                                               min="0" max="${availableToReturn}" value="${defaultValueQty}" required>
                                         ${isQtyInvalid ? `<span class="invalid-feedback d-block">${qtyErrorMessage}</span>` : ''}
                                    </td>
                                    <td>
                                        <input type="text" name="items[${item.id}][alasan]"
                                               class="form-control ${isAlasanInvalid ? 'is-invalid' : ''}"
                                               value="${defaultValueAlasan}" placeholder="Alasan retur (Opsional)">
                                         ${isAlasanInvalid ? `<span class="invalid-feedback d-block">${alasanErrorMessage}</span>` : ''}
                                    </td>
                                </tr>`;
                                    tableBody.append(row);
                                }
                            });

                            if (!hasItemsToReturn) {
                                tableBody.html(
                                    '<tr><td colspan="4" class="text-center text-info">Semua item gagal QC dari penerimaan ini sudah diretur.</td></tr>'
                                    );
                                submitButton.prop('disabled', true);
                            } else {
                                submitButton.prop('disabled', false);
                            }

                        } else {
                            tableBody.html(
                                '<tr><td colspan="4" class="text-center text-info">Tidak ada item Gagal QC yang bisa diretur dari penerimaan ini.</td></tr>'
                                );
                            submitButton.prop('disabled', true);
                        }
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                        tableBody.html(
                            '<tr><td colspan="4" class="text-center text-danger">Gagal memuat item: ' + (
                                jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON
                                .message : 'Silakan cek log.') + '</td></tr>');
                        submitButton.prop('disabled', true);
                    });
            }

            receivingSelect.on('select2:select', function(e) {
                var data = e.params.data;
                if (data && data.id) {
                    loadItems(data.id);
                }
            });
            receivingSelect.on('select2:unselect', function(e) {
                loadItems(null);
            });

            let initialReceivingId = "{{ old('receiving_id') }}";
            if (initialReceivingId) {
                loadItems(initialReceivingId);
            }

            tableBody.on('input change', '.qty-retur', function() {
                let input = $(this);
                let max = parseInt(input.attr('max')) || 0;
                let val = parseInt(input.val()) || 0;
                let errorSpan = input.siblings('.invalid-feedback.dynamic-error');

                if (val > max) {
                    input.val(max);
                    input.addClass('is-invalid');
                    if (!errorSpan.length) {
                        input.after('<span class="invalid-feedback d-block dynamic-error">Tidak boleh > ' +
                            max + '</span>');
                    } else {
                        errorSpan.text('Tidak boleh > ' + max).show();
                    }
                } else if (val < 0) {
                    input.val(0);
                    input.addClass('is-invalid');
                    if (!errorSpan.length) {
                        input.after(
                            '<span class="invalid-feedback d-block dynamic-error">Tidak boleh < 0</span>'
                            );
                    } else {
                        errorSpan.text('Tidak boleh < 0').show();
                    }
                } else {
                    input.removeClass('is-invalid');
                    errorSpan.remove();
                    input.siblings('.invalid-feedback').not('.dynamic-error').hide();
                }
            });

            tableBody.on('input', 'input[name$="[alasan]"]', function() {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').hide();
            });

        });
    </script>
@stop

@section('css')
    <style>
        .form-control-plaintext {
            padding-top: .375rem;
            padding-bottom: .375rem;
            margin-bottom: 0;
            line-height: 1.5;
            background-color: transparent;
            border: solid transparent;
            border-width: 1px 0;
        }

        .invalid-feedback.d-block {
            display: block !important;
        }

        .select2-container--bootstrap4 .select2-selection--single {
            height: calc(2.25rem + 2px) !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5 !important;
            padding-top: calc(.375rem + 1px) !important;
            padding-left: .75rem !important;
            padding-right: 1.75rem !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem + 2px) !important;
        }

        #return-items-table th,
        #return-items-table td {
            vertical-align: middle;
        }

        #return-items-table .qty-retur {
            width: 100px;
            text-align: center;
        }
    </style>
@stop
