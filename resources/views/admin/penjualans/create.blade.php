@extends('adminlte::page')

@section('title', 'Buat Penjualan Baru')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Penjualan Baru</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.penjualans.store') }}" method="POST">
        @csrf
        <div class="card-body">
            {{-- Tampilkan Error Validasi & Session --}}
            @if(session('error'))
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
                            @if(preg_match('/^items\.\d+\..*/', $error))
                                {{-- Jika ini adalah error item, set flag dan jangan tampilkan di list atas --}}
                                @php $hasItemError = true; @endphp
                            @else
                                {{-- Tampilkan error umum (bukan error item) --}}
                                <li>{{ $error }}</li>
                            @endif
                        @endforeach
                        {{-- Jika ada error item, tampilkan pesan ringkasan --}}
                        @if($hasItemError)
                            <li>Terdapat kesalahan pada input item retur. Silakan periksa detail di bawah tabel.</li>
                        @endif
                    </ul>
                </div>
            @endif

            {{-- Header Penjualan --}}
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="lokasi_id">Lokasi Penjualan <span class="text-danger">*</span></label>
                    @php
                        $isSuperUser = $allLokasi->isNotEmpty();
                        $selectedLokasiId = old('lokasi_id', $userLokasi ? $userLokasi->id : null);
                    @endphp

                    @if($isSuperUser)
                        {{-- Dropdown untuk SA/PIC --}}
                        <select name="lokasi_id" id="lokasi_id" class="form-control select2bs4 @error('lokasi_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Lokasi --</option>
                            @foreach($allLokasi as $lok)
                                <option value="{{ $lok->id }}" {{ $selectedLokasiId == $lok->id ? 'selected' : '' }}>
                                    {{ $lok->nama_lokasi }} ({{ $lok->kode_lokasi }})
                                </option>
                            @endforeach
                        </select>
                    @elseif($userLokasi)
                        {{-- Field Readonly untuk staf biasa --}}
                        <input type="text" class="form-control" value="{{ $userLokasi->nama_lokasi }} ({{ $userLokasi->kode_lokasi }})" readonly>
                        <input type="hidden" id="lokasi_id" name="lokasi_id" value="{{ $userLokasi->id }}">
                    @else
                        {{-- Fallback/Error (seharusnya sudah ditangani controller) --}}
                        <input type="text" class="form-control is-invalid" value="Lokasi tidak ditemukan" readonly>
                    @endif

                    @error('lokasi_id')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>

                <div class="col-md-4 form-group">
                    <label for="konsumen_id">Konsumen <span class="text-danger">*</span></label>
                    <select class="form-control select2bs4 @error('konsumen_id') is-invalid @enderror" id="konsumen_id" name="konsumen_id" required>
                        <option value="">Pilih Konsumen</option>
                        @foreach($konsumens as $konsumen)
                            <option value="{{ $konsumen->id }}" {{ old('konsumen_id') == $konsumen->id ? 'selected' : '' }}>{{ $konsumen->nama_konsumen }}</option>
                        @endforeach
                    </select>
                     @error('konsumen_id')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label for="tanggal_jual">Tanggal Jual <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('tanggal_jual') is-invalid @enderror" id="tanggal_jual" name="tanggal_jual" value="{{ old('tanggal_jual', date('Y-m-d')) }}" required>
                     @error('tanggal_jual')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
            <hr>

            <h5>Detail Part yang Akan Dijual</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="parts-table">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 40%;">Part <span class="text-danger">*</span></th>
                            <th style="width: 15%;" class="text-center">Stok Tersedia</th>
                            <th style="width: 15%;" class="text-center">Qty Jual <span class="text-danger">*</span></th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Subtotal</th>
                            <th style="width: 50px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="parts-container">
                         {{-- Render baris dari old input jika ada --}}
                         @if(old('items'))
                             @foreach(old('items') as $index => $item)
                                 @php
                                     // Pastikan part_id ada sebelum query
                                     $part = !empty($item['part_id']) ? \App\Models\Part::find($item['part_id']) : null;
                                     $harga = $part ? $part->harga_satuan : 0;
                                     $qty = $item['qty_jual'] ?? 1;
                                     $subtotal = $harga * $qty;
                                 @endphp
                                 <tr class="part-row">
                                     <td>
                                         <select name="items[{{ $index }}][part_id]" class="form-control part-select select2-lazy" required>
                                              @if($part)
                                              {{-- Pre-load opsi yang dipilih saat old input --}}
                                              <option value="{{ $part->id }}" selected data-harga="{{ $harga }}" data-stok="0">
                                                  {{ $part->kode_part }} - {{ $part->nama_part }}
                                              </option>
                                              @else
                                               <option value="">Pilih Part</option>
                                              @endif
                                         </select>
                                         @error("items.{$index}.part_id") <span class="invalid-feedback d-block">{{$message}}</span> @enderror
                                     </td>
                                     <td>
                                         <input type="number" class="form-control stok-tersedia" readonly>
                                     </td>
                                     <td>
                                         <input type="number" name="items[{{ $index }}][qty_jual]" class="form-control qty-input @error("items.{$index}.qty_jual") is-invalid @enderror" value="{{ $qty }}" min="1" required>
                                         @error("items.{$index}.qty_jual") <span class="invalid-feedback d-block">{{$message}}</span> @enderror
                                     </td>
                                     <td>
                                         <input type="text" class="form-control harga-text text-right" value="{{ number_format($harga, 0, ',', '.') }}" readonly>
                                         <input type="hidden" class="harga-hidden" value="{{ $harga }}">
                                     </td>
                                     <td>
                                         <input type="text" class="form-control subtotal-text text-right" value="{{ number_format($subtotal, 0, ',', '.') }}" readonly>
                                     </td>
                                     <td>
                                         <button type="button" class="btn btn-danger btn-sm remove-part-btn"><i class="fas fa-trash"></i></button>
                                     </td>
                                 </tr>
                             @endforeach
                         @else
                             {{-- Baris pertama jika tidak ada old input --}}
                             <tr class="part-row">
                                 <td>
                                     <select name="items[0][part_id]" class="form-control part-select select2-lazy" required>
                                         <option value="">Pilih Lokasi Dahulu</option>
                                     </select>
                                 </td>
                                 <td>
                                     <input type="number" class="form-control stok-tersedia" readonly>
                                 </td>
                                 <td>
                                     <input type="number" name="items[0][qty_jual]" class="form-control qty-input" min="1" required>
                                 </td>
                                 <td>
                                     <input type="text" class="form-control harga-text text-right" value="0" readonly>
                                      <input type="hidden" class="harga-hidden" value="0">
                                 </td>
                                 <td>
                                     <input type="text" class="form-control subtotal-text text-right" value="0" readonly>
                                 </td>
                                 <td>
                                     <button type="button" class="btn btn-danger btn-sm remove-part-btn"><i class="fas fa-trash"></i></button>
                                 </td>
                             </tr>
                         @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <button type="button" class="btn btn-secondary btn-sm" id="add-part-btn">
                                    <i class="fas fa-plus"></i> Tambah Baris
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Tampilan Total --}}
            <div class="row mt-4">
                <div class="col-md-6 offset-md-6">
                    <div class="table-responsive">
                        <table class="table">
                            <tr><th style="width:50%">Subtotal:</th><td class="text-right" id="subtotal-text">Rp 0</td></tr>
                            <tr><th>Total Diskon:</th><td class="text-right text-success" id="diskon-text">Rp 0</td></tr>
                            <tr><th>Total Keseluruhan:</th><td class="text-right h4" id="total-text">Rp 0</td></tr>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <div class="card-footer text-right">
            <button type="submit" class="btn btn-primary">Simpan Penjualan</button>
            <a href="{{ route('admin.penjualans.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    let itemIndex = {{ count(old('items', [])) > 0 ? count(old('items', [])) : 1 }};
    let partsDataCache = {};
    let activeLokasiId = null;

    $('.select2bs4').select2({ theme: 'bootstrap4' });

    function initSelect2(element) {
         $(element).select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih Part',
            allowClear: true,
            ajax: {
                url: function() {
                     let lokasiId = $('#lokasi_id').val();
                     if (!lokasiId) return '';
                     return `{{ route('admin.api.lokasi.parts', ['lokasi' => ':id']) }}`.replace(':id', lokasiId);
                },
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    let lokasiId = $('#lokasi_id').val();
                    if (lokasiId && !partsDataCache[lokasiId]) {
                        partsDataCache[lokasiId] = {};
                         data.forEach(part => partsDataCache[lokasiId][part.id] = part);
                    }
                    return {
                        results: $.map(data, function (part) {
                            return {
                                id: part.id,
                                text: `${part.kode_part} - ${part.nama_part} (Stok: ${part.total_stock})`,
                                partData: part
                            };
                        })
                    };
                },
                cache: true
            }
        });
    }

    $('.select2-lazy').each(function() {
        initSelect2(this);
        let selectedPartId = $(this).val();
        if(selectedPartId) {
             updateRowData($(this).closest('tr'), selectedPartId, false); // false = jangan reset qty
        }
    });

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    function calculateTotal() {
        let subtotal = 0;
        $('#parts-container .part-row').each(function() {
            let row = $(this);
            let harga = parseFloat(row.find('.harga-hidden').val()) || 0;
            let qty = parseInt(row.find('.qty-input').val()) || 0;
            let rowSubtotal = harga * qty;

            row.find('.subtotal-text').val(formatRupiah(rowSubtotal));
            subtotal += rowSubtotal;
        });

        $('#subtotal-text').text(formatRupiah(subtotal));
        $('#diskon-text').text(formatRupiah(0));
        $('#total-text').text(formatRupiah(subtotal));
    }

    function resetPartSelectors() {
        let lokasiId = $('#lokasi_id').val();
        let konsumenId = $('#konsumen_id').val();

        if (lokasiId != activeLokasiId) {
             partsDataCache[lokasiId] = null;
             activeLokasiId = lokasiId;
        }

        $('#parts-container .part-select').each(function() {
             $(this).empty().trigger('change');
             if (!lokasiId || !konsumenId) {
                 $(this).prop('disabled', true).select2({placeholder: 'Pilih Lokasi & Konsumen dahulu', theme: 'bootstrap4'});
             } else {
                 $(this).prop('disabled', false);
                 initSelect2(this);
             }
        });

         $('#parts-container .part-row').each(function() {
             let row = $(this);
             row.find('.stok-tersedia').val('');
             row.find('.qty-input').val('').attr('max', '');
             row.find('.harga-text').val('0');
             row.find('.harga-hidden').val('0');
             row.find('.subtotal-text').val('0');
         });

        calculateTotal();
    }

     function updateRowData(row, partId, resetQty = true) {
         let lokasiId = $('#lokasi_id').val();
         let partData = null;

         if (partsDataCache[lokasiId] && partsDataCache[lokasiId][partId]) {
             partData = partsDataCache[lokasiId][partId];
         }

         if (!partData) {
             let selectData = row.find('.part-select').select2('data')[0];
             if (selectData && selectData.partData) {
                 partData = selectData.partData;
             }
         }

         if (!partData && row.find('.harga-hidden').val() > 0 && !resetQty) {
              let harga = row.find('.harga-hidden').val();
               let url = `{{ route('admin.api.lokasi.parts', ['lokasi' => ':id']) }}`.replace(':id', lokasiId);
               $.getJSON(url, function(parts) {
                   let stok = 0;
                   (parts || []).forEach(part => {
                       if(part.id == partId) stok = part.total_stock;
                   });
                   row.find('.stok-tersedia').val(stok);
                   row.find('.qty-input').attr('max', stok);
               });
               row.find('.harga-text').val(formatRupiah(harga));
               calculateTotal();
               return;
         }

         if (partData) {
             let stok = parseInt(partData.total_stock) || 0;
             let harga = parseFloat(partData.harga_satuan) || 0;

             row.find('.stok-tersedia').val(stok);
             row.find('.qty-input').attr('max', stok);
             if(resetQty) { // Hanya reset qty jika diminta
                row.find('.qty-input').val('1');
             }
             row.find('.harga-text').val(formatRupiah(harga));
             row.find('.harga-hidden').val(harga);
             calculateTotal();
         } else if (partId === "") {
             row.find('.stok-tersedia').val('');
             row.find('.qty-input').val('').attr('max', '');
             row.find('.harga-text').val('0');
             row.find('.harga-hidden').val('0');
             calculateTotal();
         }
     }

    // === EVENT LISTENERS ===
    $('#lokasi_id, #konsumen_id').on('select2:select select2:unselect', function() {
        resetPartSelectors();
    });

    $('#add-part-btn').on('click', function() {
        let newIndex = itemIndex++;
        let newRow = `
            <tr class="part-row">
                <td>
                    <select name="items[${newIndex}][part_id]" class="form-control part-select select2-lazy" required>
                        <option value="">Pilih Part</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control stok-tersedia" readonly>
                </td>
                <td>
                    <input type="number" name="items[${newIndex}][qty_jual]" class="form-control qty-input" min="1" required>
                </td>
                <td>
                    <input type="text" class="form-control harga-text text-right" value="0" readonly>
                    <input type="hidden" class="harga-hidden" value="0">
                </td>
                <td>
                    <input type="text" class="form-control subtotal-text text-right" value="0" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-part-btn"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;

        $('#parts-container').append(newRow);
        initSelect2($(`#parts-container tr:last-child .select2-lazy`));
    });

    $('#parts-container').on('click', '.remove-part-btn', function() {
        $(this).closest('tr').remove();
        calculateTotal();
    });

    $('#parts-container').on('select2:select', '.part-select', function(e) {
        let row = $(this).closest('tr');
        let partId = $(this).val();
        updateRowData(row, partId, true); // true = reset qty ke 1
    });

     $('#parts-container').on('select2:unselect', '.part-select', function(e) {
        let row = $(this).closest('tr');
        updateRowData(row, "");
    });

    $('#parts-container').on('input', '.qty-input', function() {
        let row = $(this).closest('tr');
        let qty = parseInt($(this).val()) || 0;
        let max = parseInt($(this).attr('max')) || 0;

        if (max > 0 && qty > max) { // Hanya validasi jika max > 0
            $(this).val(max);
             // Ganti alert dengan feedback non-blocking
             $(this).addClass('is-invalid');
             $(this).siblings('.invalid-feedback.dynamic-error').remove();
             $(this).after('<span class="invalid-feedback d-block dynamic-error">Stok hanya ${max}</span>');
        } else if (qty < 0) {
            $(this).val(1);
        } else {
             $(this).removeClass('is-invalid');
             $(this).siblings('.invalid-feedback.dynamic-error').remove();
        }

        calculateTotal();
    });

    // Panggil kalkulasi total saat pertama kali load (untuk old input)
    calculateTotal();

    // Panggil load parts/update stok pertama kali jika header sudah terisi
    if ($('#lokasi_id').val() && $('#konsumen_id').val()) {
         activeLokasiId = $('#lokasi_id').val();
         // Update stok & harga untuk baris-baris dari old() input
         $('.part-row').each(function(){
              let row = $(this);
              let partId = row.find('.part-select').val();
              if(partId){
                   updateRowData(row, partId, false); // false = jangan reset qty
              }
         });
    } else if ($('#lokasi_id').val()) {
        // Jika hanya lokasi terisi
        activeLokasiId = $('#lokasi_id').val();
        // Inisialisasi part select di baris pertama jika ada
        initSelect2($('#parts-container tr:first-child .select2-lazy'));
    }

});
</script>
@stop

@section('css')
<style>
    /* Mengatasi tampilan input readonly agar terlihat seperti teks biasa */
    .form-control-plaintext {
        padding-top: .375rem;
        padding-bottom: .375rem;
        margin-bottom: 0;
        line-height: 1.5;
        background-color: transparent;
        border: solid transparent;
        border-width: 1px 0;
        box-shadow: none; /* Tambahan: hapus shadow jika ada */
    }
    .invalid-feedback.d-block {
        display: block !important; /* Memastikan pesan error JS terlihat */
    }

    /* ++ PERBAIKAN CSS SELECT2 ++ */

    /* Menyelaraskan tinggi Select2 dengan input form-control standar (tinggi: calc(2.25rem + 2px)) */
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }

    /* Menyelaraskan teks yang dipilih di dalam box */
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        /* Padding-top disesuaikan agar teks center secara vertikal */
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        padding-left: 0.75rem !important; /* Padding kiri standar BS4 */
    }

    /* Menyelaraskan panah dropdown */
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem); /* Buat tinggi arrow sama dengan tinggi input (dikurangi border) */
        position: absolute;
        top: 50%;
        right: 0.75rem;
        transform: translateY(-50%); /* Center vertikal */
    }

    /* Menyelaraskan tombol 'x' (clear) */
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__clear {
        position: absolute;
        top: 50%;
        right: 2.25rem; /* Posisikan di kiri panah */
        transform: translateY(-50%);
        line-height: 1; /* Pastikan 'x' nya center */
        padding: 0.25rem 0.5rem; /* Beri sedikit padding agar mudah diklik */
        margin: 0;
    }

    /* Menangani Select2 di dalam tabel (part-select) */
    #parts-table .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.125rem + 2px) !important; /* Sesuaikan dengan tinggi form-control-sm jika perlu */
    }
    #parts-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
         padding-top: 0.25rem !important; /* Padding-top untuk form-sm */
         line-height: 1.5;
    }
     #parts-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(2.125rem);
    }

    /* Gaya untuk input di dalam tabel */
     #parts-table th, #parts-table td {
         vertical-align: middle;
    }
     #parts-table .qty-input {
         width: 100px;
         text-align: center;
    }
     #parts-table .stok-tersedia {
         width: 100px;
         text-align: center;
         background-color: #e9ecef;
         border: none;
         box-shadow: none;
    }
     #parts-table .harga-text, #parts-table .subtotal-text {
         background-color: #e9ecef;
         border: none;
         box-shadow: none;
         text-align: right;
    }
</style>
@stop
