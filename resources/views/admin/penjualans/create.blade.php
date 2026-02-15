@extends('adminlte::page')

@section('title', 'Buat Penjualan Baru')
@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-cash-register text-success mr-2"></i> Point of Sales (POS)</h1>
    </div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Tampilkan Error Validasi --}}
        @if($errors->any())
            <x-adminlte-alert theme="danger" title="Peringatan!" dismissable>
                <ul class="mb-0">
                   @foreach($errors->all() as $error)
                       <li>{{ $error }}</li>
                   @endforeach
                </ul>
            </x-adminlte-alert>
        @endif

        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal!" dismissable>
                {{ session('error') }}
            </x-adminlte-alert>
        @endif

        <div class="card card-outline card-success shadow-sm">
            <form action="{{ route('admin.penjualans.store') }}" method="POST" id="sales-form">
                @csrf
                <div class="card-body">
                    
                    {{-- INFO PENJUALAN --}}
                    <div class="card card-light mb-4">
                        <div class="card-header">
                            <h3 class="card-title text-muted"><i class="fas fa-user-circle mr-1"></i> Data Pelanggan & Transaksi</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Nama Konsumen <span class="text-danger">*</span></label>
                                        <input type="text" name="customer_name" class="form-control" placeholder="Nama Pelanggan / Bengkel" required value="{{ old('customer_name') }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tipe Konsumen <span class="text-danger">*</span></label>
                                        <select name="tipe_konsumen" class="form-control" required>
                                            <option value="RETAIL" {{ old('tipe_konsumen') == 'RETAIL' ? 'selected' : '' }}>RETAIL (Perorangan)</option>
                                            <option value="BENGKEL" {{ old('tipe_konsumen') == 'BENGKEL' ? 'selected' : '' }}>BENGKEL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>No. Telepon / HP</label>
                                        <input type="text" name="telepon" class="form-control" placeholder="08..." value="{{ old('telepon') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Alamat</label>
                                        <textarea name="alamat" class="form-control" rows="1" placeholder="Alamat Singkat (Opsional)">{{ old('alamat') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tanggal Transaksi</label>
                                        <input type="date" name="tanggal_jual" class="form-control" value="{{ old('tanggal_jual', $today) }}" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Lokasi Stok</label>
                                        <input type="text" class="form-control bg-light" value="{{ $lokasi->nama_lokasi ?? 'Pusat' }}" readonly>
                                        <input type="hidden" id="lokasi_id" value="{{ $lokasi->id }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TABEL ITEM --}}
                    <h5 class="text-secondary"><i class="fas fa-shopping-cart mr-2"></i>Keranjang Belanja</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-hover" id="items-table">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 40%">Barang / Part</th>
                                    <th style="width: 15%" class="text-center">Stok Gudang</th>
                                    <th style="width: 15%" class="text-center">Qty Beli</th>
                                    <th style="width: 15%" class="text-right">Harga (@)</th>
                                    <th style="width: 15%" class="text-right">Subtotal</th>
                                    <th style="width: 50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- JS akan mengisi ini --}}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6">
                                        <button type="button" class="btn btn-outline-success btn-sm btn-block border-dashed" id="btn-add-row">
                                            <i class="fas fa-plus-circle"></i> Tambah Baris Barang
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- RINGKASAN PEMBAYARAN --}}
                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <table class="table table-sm table-borderless m-0">
                                        <tr>
                                            <th class="text-right" style="width: 50%">Subtotal:</th>
                                            <td class="text-right"><h5 class="font-weight-bold" id="label-subtotal">Rp 0</h5></td>
                                        </tr>
                                        <tr>
                                            <th class="align-middle text-right">Diskon (Rp):</th>
                                            <td>
                                                <input type="text" name="nama_diskon" class="form-control form-control-sm mb-1" placeholder="Keterangan Diskon (Opsional)" value="{{ old('nama_diskon') }}">
                                                <input type="number" name="nilai_diskon" id="input-diskon" class="form-control form-control-sm text-right font-weight-bold text-danger" value="{{ old('nilai_diskon', 0) }}" min="0">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="text-right align-middle">Pajak:</th>
                                            <td class="text-right">
                                                <div class="custom-control custom-checkbox text-right">
                                                    <input type="checkbox" class="custom-control-input" id="check-ppn" name="ppn_check" value="1" {{ old('ppn_check') ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="check-ppn">PPN 11%</label>
                                                </div>
                                                <div id="label-ppn" class="text-muted small mt-1">Rp 0</div>
                                            </td>
                                        </tr>
                                        <tr class="border-top border-secondary">
                                            <th class="text-right align-middle pt-3"><h4>Total Akhir:</h4></th>
                                            <td class="text-right pt-3"><h3 class="font-weight-bold text-success" id="label-grand-total">Rp 0</h3></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <a href="{{ route('admin.penjualans.index') }}" class="btn btn-default">
                        <i class="fas fa-times mr-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow" id="btn-save" disabled>
                        <i class="fas fa-check mr-2"></i> PROSES TRANSAKSI
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; padding-top: 5px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .border-dashed { border-style: dashed !important; border-width: 2px !important; }
    input:disabled { background-color: #e9ecef !important; }
</style>
@endpush

@push('js')
<script>
$(document).ready(function() {
    let rowIndex = 0;

    // Format Rupiah Helper
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    // Tambah Baris Baru
    function addRow() {
        rowIndex++;
        let row = `
            <tr id="row-${rowIndex}">
                <td>
                    <select name="items[${rowIndex}][barang_id]" class="form-control select-barang" data-row="${rowIndex}" required style="width: 100%;">
                        <option value="">Cari Barang (Kode/Nama)...</option>
                    </select>
                </td>
                <td class="text-center align-middle">
                    <span id="stock-badge-${rowIndex}" class="badge badge-secondary" style="font-size:1em;">0</span>
                    <input type="hidden" id="stock-val-${rowIndex}" value="0">
                </td>
                <td>
                    <input type="number" name="items[${rowIndex}][qty]" class="form-control text-center input-qty font-weight-bold" data-row="${rowIndex}" value="1" min="1" required disabled>
                </td>
                <td class="text-right align-middle">
                    <span id="price-text-${rowIndex}">0</span>
                    <input type="hidden" id="price-input-${rowIndex}" value="0">
                </td>
                <td class="text-right align-middle font-weight-bold text-primary">
                    <span id="subtotal-${rowIndex}">0</span>
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove" data-row="${rowIndex}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#items-table tbody').append(row);
        initSelect2(rowIndex);
        calculateTotal();
    }

    // Inisialisasi Select2 Ajax
    function initSelect2(index) {
        $(`.select-barang[data-row="${index}"]`).select2({
            theme: 'bootstrap4',
            placeholder: 'Ketik Nama/Kode Part...',
            allowClear: true,
            ajax: {
                url: "{{ route('admin.api.penjualan.items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        lokasi_id: $('#lokasi_id').val() // Kirim ID lokasi
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true
            }
        });
    }

    // Event: Barang Dipilih
    $(document).on('select2:select', '.select-barang', function(e) {
        let row = $(this).data('row');
        let data = e.params.data;
        let price = parseFloat(data.price) || 0;
        let stock = parseInt(data.stock) || 0;

        // Update UI
        $(`#price-text-${row}`).text(formatRupiah(price));
        $(`#price-input-${row}`).val(price);
        $(`#stock-val-${row}`).val(stock);

        let badge = $(`#stock-badge-${row}`);
        badge.text(stock);

        let qtyInput = $(`.input-qty[data-row="${row}"]`);

        if(stock > 0) {
            badge.removeClass('badge-danger badge-secondary').addClass('badge-success');
            qtyInput.prop('disabled', false).attr('max', stock).val(1);
        } else {
            badge.removeClass('badge-success badge-secondary').addClass('badge-danger');
            qtyInput.prop('disabled', true).val(0);
            Swal.fire({
                icon: 'error',
                title: 'Stok Habis!',
                text: 'Barang ini tidak tersedia di lokasi ini.'
            });
        }
        calculateRow(row);
    });

    // Event: Qty Berubah
    $(document).on('input', '.input-qty', function() {
        let row = $(this).data('row');
        let max = parseInt($(this).attr('max')) || 0;
        let val = parseInt($(this).val());

        if (isNaN(val) || val < 1) {
            $(this).val(1);
            val = 1;
        }

        if(val > max) {
            Swal.fire({
                icon: 'warning',
                title: 'Stok Tidak Cukup',
                text: `Maksimal pembelian adalah ${max} unit`
            });
            $(this).val(max);
        }
        calculateRow(row);
    });

    // Event: Hapus Baris
    $(document).on('click', '.btn-remove', function() {
        let row = $(this).data('row');
        $(`#row-${row}`).remove();
        calculateTotal();
    });

    // Hitung Subtotal Baris
    function calculateRow(row) {
        let qty = parseInt($(`.input-qty[data-row="${row}"]`).val()) || 0;
        let price = parseFloat($(`#price-input-${row}`).val()) || 0;
        let subtotal = qty * price;
        $(`#subtotal-${row}`).text(formatRupiah(subtotal));
        calculateTotal();
    }

    // Hitung Grand Total Global
    function calculateTotal() {
        let subtotalGlobal = 0;
        let itemCount = 0;

        $('.input-qty').each(function() {
            if (!$(this).prop('disabled')) {
                let row = $(this).data('row');
                let qty = parseInt($(this).val()) || 0;
                let price = parseFloat($(`#price-input-${row}`).val()) || 0;
                subtotalGlobal += (qty * price);
                itemCount++;
            }
        });

        let diskon = parseFloat($('#input-diskon').val()) || 0;
        let isPpn = $('#check-ppn').is(':checked');

        // Validasi Diskon
        if(diskon > subtotalGlobal) {
            diskon = subtotalGlobal;
            $('#input-diskon').val(diskon);
        }

        let dpp = subtotalGlobal - diskon;
        let ppnValue = isPpn ? (dpp * 0.11) : 0;
        let grandTotal = dpp + ppnValue;

        // Update Label
        $('#label-subtotal').text(formatRupiah(subtotalGlobal));
        $('#label-ppn').text(isPpn ? formatRupiah(ppnValue) : 'Rp 0');
        $('#label-grand-total').text(formatRupiah(grandTotal));

        // Enable/Disable tombol save
        if(subtotalGlobal > 0 && itemCount > 0) {
            $('#btn-save').prop('disabled', false);
        } else {
            $('#btn-save').prop('disabled', true);
        }
    }

    $('#btn-add-row').click(addRow);
    $('#input-diskon').on('input', calculateTotal);
    $('#check-ppn').on('change', calculateTotal);

    // Tambah 1 baris default saat load
    addRow();
});
</script>
@endpush