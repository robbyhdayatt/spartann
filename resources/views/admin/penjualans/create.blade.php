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
                                @php $hasItemError = true; @endphp
                            @else
                                <li>{{ $error }}</li>
                            @endif
                        @endforeach
                        @if($hasItemError)
                            <li>Terdapat kesalahan pada input item. Silakan periksa detail di bawah tabel.</li>
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
                        <select name="lokasi_id" id="lokasi_id" class="form-control select2bs4 @error('lokasi_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Lokasi --</option>
                            @foreach($allLokasi as $lok)
                                <option value="{{ $lok->id }}" {{ $selectedLokasiId == $lok->id ? 'selected' : '' }}>
                                    {{ $lok->nama_lokasi }} ({{ $lok->kode_lokasi }})
                                </option>
                            @endforeach
                        </select>
                    @elseif($userLokasi)
                        <input type="text" class="form-control" value="{{ $userLokasi->nama_lokasi }} ({{ $userLokasi->kode_lokasi }})" readonly>
                        <input type="hidden" id="lokasi_id" name="lokasi_id" value="{{ $userLokasi->id }}">
                    @else
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

            <h5>Detail Item/Jasa yang Dijual</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="items-table">
                    <thead class="thead-light">
                        <tr>
                            {{-- ++ DIUBAH: Lebar kolom disesuaikan --}}
                            <th style="width: 55%;">Item / Jasa (dari tabel Converts) <span class="text-danger">*</span></th>
                            {{-- ++ DIHAPUS: Kolom Stok Tersedia --}}
                            <th style="width: 10%;" class="text-center">Qty</th>
                            <th style="width: 15%;" class="text-right">Harga Satuan</th>
                            <th style="width: 15%;" class="text-right">Subtotal</th>
                            <th style="width: 50px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="items-container">
                         {{-- Render baris dari old input jika ada --}}
                         @if(old('items'))
                             @foreach(old('items') as $index => $item)
                                 @php
                                     $convert = !empty($item['convert_id']) ? \App\Models\Convert::find($item['convert_id']) : null;
                                    //  $namaJob = $convert ? $convert->nama_job : 'N/A';
                                     $partName = $convert ? $convert->part_name : 'N/A';
                                     $qty = $convert ? $convert->quantity : 0;
                                     $harga = $convert ? $convert->harga_jual : 0;
                                     $subtotal = $harga * $qty;
                                 @endphp
                                 <tr class="item-row">
                                     <td>
                                         <select name="items[{{ $index }}][convert_id]" class="form-control item-select select2-lazy" required>
                                              @if($convert)
                                              <option value="{{ $convert->id }}" selected
                                                  data-qty="{{ $qty }}"
                                                  data-harga="{{ $harga }}">
                                                  {{ $partName }}
                                              </option>
                                              @endif
                                         </select>
                                         @error("items.{$index}.convert_id") <span class="invalid-feedback d-block">{{$message}}</span> @enderror
                                     </td>
                                     {{-- ++ DIHAPUS: Kolom Stok Tersedia --}}
                                     <td>
                                         <input type="number" class="form-control qty-input text-center" value="{{ $qty }}" readonly>
                                     </td>
                                     <td>
                                         <input type="text" class="form-control harga-text text-right" value="{{ number_format($harga, 0, ',', '.') }}" readonly>
                                     </td>
                                     <td>
                                         <input type="text" class="form-control subtotal-text text-right" value="{{ number_format($subtotal, 0, ',', '.') }}" readonly>
                                     </td>
                                     <td>
                                         <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button>
                                     </td>
                                 </tr>
                             @endforeach
                         @else
                             {{-- Baris pertama jika tidak ada old input --}}
                             <tr class="item-row">
                                 <td>
                                     <select name="items[0][convert_id]" class="form-control item-select select2-lazy" required>
                                         <option value="">Pilih Item/Jasa</option>
                                     </select>
                                 </td>
                                 {{-- ++ DIHAPUS: Kolom Stok Tersedia --}}
                                 <td>
                                     <input type="number" class="form-control qty-input text-center" value="0" readonly>
                                 </td>
                                 <td>
                                     <input type="text" class="form-control harga-text text-right" value="0" readonly>
                                 </td>
                                 <td>
                                     <input type="text" class="form-control subtotal-text text-right" value="0" readonly>
                                 </td>
                                 <td>
                                     <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button>
                                 </td>
                             </tr>
                         @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            {{-- ++ DIUBAH: Colspan disesuaikan --}}
                            <td colspan="4">
                                <button type="button" class="btn btn-secondary btn-sm" id="add-item-btn">
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
    let convertsDataCache = {};
    let activeLokasiId = null;

    $('.select2bs4').select2({ theme: 'bootstrap4' });

    function initSelect2(element) {
         $(element).select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih Item/Jasa',
            allowClear: true,
            ajax: {
                url: `{{ route('admin.api.get-convert-items') }}`,
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    if (Object.keys(convertsDataCache).length === 0) {
                         (data || []).forEach(item => convertsDataCache[item.id] = item.data);
                    }
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });
    }

    $('.select2-lazy').each(function() {
        initSelect2(this);
        let selectedConvertId = $(this).val();
        if(selectedConvertId) {
             let option = $(this).find('option:selected');
             let data = {
                 quantity: option.data('qty'),
                 harga_jual: option.data('harga')
             };
             updateRowData($(this).closest('tr'), data, false);
        }
    });

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    function calculateTotal() {
        let subtotal = 0;
        $('#items-container .item-row').each(function() {
            let row = $(this);
            let subtotalText = row.find('.subtotal-text').val() || '0';
            let rowSubtotal = parseFloat(subtotalText.replace(/[^0-9,-]+/g, '').replace(',', '.')) || 0;
            subtotal += rowSubtotal;
        });

        $('#subtotal-text').text(formatRupiah(subtotal));
        $('#diskon-text').text(formatRupiah(0));
        $('#total-text').text(formatRupiah(subtotal));
    }

    function updateRowData(row, itemData, resetQty = true) {
         if (itemData) {
             let qty = parseInt(itemData.quantity) || 0;
             let harga = parseFloat(itemData.harga_jual) || 0;
             let subtotal = qty * harga;

             row.find('.qty-input').val(qty);
             row.find('.harga-text').val(formatRupiah(harga));
             row.find('.subtotal-text').val(formatRupiah(subtotal));
         } else {
             row.find('.qty-input').val('0');
             row.find('.harga-text').val('0');
             row.find('.subtotal-text').val('0');
         }
         calculateTotal();
     }

    // === EVENT LISTENERS ===

    // 1. Tambah Baris
    $('#add-item-btn').on('click', function() {
        let newIndex = itemIndex++;
        let newRow = `
            <tr class="item-row">
                <td>
                    <select name="items[${newIndex}][convert_id]" class="form-control item-select select2-lazy" required>
                        <option value="">Pilih Item/Jasa</option>
                    </select>
                </td>
                {{-- ++ DIHAPUS: Kolom Stok Tersedia --}}
                <td>
                    <input type="number" class="form-control qty-input text-center" value="0" readonly>
                </td>
                <td>
                    <input type="text" class="form-control harga-text text-right" value="0" readonly>
                </td>
                <td>
                    <input type="text" class="form-control subtotal-text text-right" value="0" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;

        $('#items-container').append(newRow);
        initSelect2($(`#items-container tr:last-child .select2-lazy`));
    });

    // 2. Hapus Baris
    $('#items-container').on('click', '.remove-item-btn', function() {
        $(this).closest('tr').remove();
        calculateTotal();
    });

    // 3. Saat Item di satu baris dipilih
    $('#items-container').on('select2:select', '.item-select', function(e) {
        let row = $(this).closest('tr');
        let selectedData = e.params.data;

        if (selectedData && selectedData.data) {
            updateRowData(row, selectedData.data, true);
        }
        else if (selectedData && selectedData.id && convertsDataCache[selectedData.id]) {
             updateRowData(row, convertsDataCache[selectedData.id], true);
        }
    });

    // 4. Saat Pilihan Item dikosongkan
     $('#items-container').on('select2:unselect', '.item-select', function(e) {
        let row = $(this).closest('tr');
        updateRowData(row, null);
    });

    // 5. Panggil kalkulasi total saat pertama kali load (untuk old input)
    calculateTotal();

    // 6. Update data untuk baris old() input (jika ada)
    const hasOldData = {{ old('items') ? 'true' : 'false' }};
    if (hasOldData) {
         $('.item-row').each(function(){
              let row = $(this);
              let convertId = row.find('.item-select').val();
              if(convertId){
                   let option = row.find('option:selected');
                   let data = {
                       quantity: option.data('qty'),
                       harga_jual: option.data('harga')
                   };
                   updateRowData(row, data, false);
              }
         });
    } else {
        // Inisialisasi baris pertama jika BUKAN old input
        initSelect2($('#items-container tr:first-child .select2-lazy'));
    }

});
</script>
@stop

@section('css')
<style>
    /* Mengatasi tampilan input readonly agar terlihat seperti teks biasa */
    .form-control-plaintext {
        padding-top: .375rem; padding-bottom: .375rem; margin-bottom: 0; line-height: 1.5;
        background-color: transparent; border: solid transparent; border-width: 1px 0; box-shadow: none;
    }
    .invalid-feedback.d-block {
        display: block !important;
    }

    /* |--------------------------------------------------------------------------
    | ++ PERBAIKAN CSS SELECT2 YANG DISEMPURNAKAN ++
    |--------------------------------------------------------------------------
    */

    /* === SELECT2 DI HEADER KARTU (.form-group) === */
    /* Menyelaraskan tinggi Select2 dengan input form-control standar */
    .form-group .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }

    /* Menyelaraskan teks yang dipilih */
    .form-group .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-top: 0.375rem !important;
        padding-bottom: 0.375rem !important;
        padding-left: 0.75rem !important;
    }

    /* Menyelaraskan panah dropdown */
    .form-group .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem - 2px); /* Tinggi dikurangi 2px border */
        position: absolute;
        top: 50%;
        right: 0.75rem;
        transform: translateY(-50%);
    }

    /* Menyelaraskan tombol 'x' (clear) */
    .form-group .select2-container--bootstrap4 .select2-selection--single .select2-selection__clear {
        position: absolute;
        top: 50%;
        right: 2.25rem; /* Posisikan di kiri panah */
        transform: translateY(-50%);
        padding: 0;
        margin: 0;
        line-height: 1; /* Pastikan 'x' nya center */
    }

    /* === SELECT2 DI DALAM TABEL (#items-table) === */
    /* Menyelaraskan tinggi Select2 dengan form-control-sm */
    #items-table .select2-container--bootstrap4 .select2-selection--single {
        height: calc(1.8125rem + 2px) !important; /* Tinggi form-control-sm */
    }
    /* Menyelaraskan teks yang dipilih (dan placeholder) */
    #items-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
         padding-top: 0.25rem !important; /* Padding-top untuk form-sm */
         line-height: 1.5;
         padding-left: .5rem;
         /* Pastikan placeholder juga terpengaruh */
         margin-top: 0;
         margin-bottom: 0;
    }
     /* Menyelaraskan panah dropdown */
     #items-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(1.8125rem); /* Tinggi dikurangi 2px border */
        position: absolute;
        top: 50%;
        right: 0.75rem;
        transform: translateY(-50%);
    }
    /* Menyelaraskan tombol 'x' (clear) */
     #items-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__clear {
         position: absolute;
         top: 50%;
         right: 1.75rem; /* Sesuaikan untuk -sm */
         transform: translateY(-50%);
         padding: 0;
         margin: 0;
         line-height: 1; /* Pastikan 'x' nya center */
    }

    /* Gaya untuk input di dalam tabel */
     #items-table th, #items-table td {
         vertical-align: middle;
    }
     #items-table .qty-input {
         width: 100px;
         text-align: center;
         height: calc(1.8125rem + 2px);
         padding: .25rem .5rem;
    }
     /* Hapus style untuk stok-tersedia karena kolomnya dihapus */
     #items-table .harga-text, #items-table .subtotal-text {
         background-color: #e9ecef;
         border: none;
         box-shadow: none;
         text-align: right;
         height: calc(1.8125rem + 2px);
         padding: .25rem .5rem;
    }
</style>
@stop
