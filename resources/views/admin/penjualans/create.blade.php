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
                            {{-- Baris 1: Nama, Tipe, Telepon --}}
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Nama Konsumen <span class="text-danger">*</span></label>
                                        <input type="text" name="customer_name" class="form-control" placeholder="Nama Pelanggan / Bengkel" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tipe Konsumen <span class="text-danger">*</span></label>
                                        <select name="tipe_konsumen" class="form-control" required>
                                            <option value="RETAIL" selected>RETAIL (Perorangan)</option>
                                            <option value="BENGKEL">BENGKEL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>No. Telepon / HP</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            </div>
                                            <input type="text" name="telepon" class="form-control" placeholder="08...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Baris 2: Alamat, Tanggal, Lokasi --}}
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Alamat</label>
                                        <textarea name="alamat" class="form-control" rows="1" placeholder="Alamat Singkat (Opsional)"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tanggal Transaksi</label>
                                        <input type="date" name="tanggal_jual" class="form-control" value="{{ $today }}" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Lokasi Stok</label>
                                        <input type="text" class="form-control bg-light" value="{{ $lokasi->nama_lokasi ?? 'Pusat' }}" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TABEL ITEM (Tidak Berubah) --}}
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-hover" id="items-table">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40%">Barang / Part</th>
                                    <th style="width: 15%" class="text-center">Stok</th>
                                    <th style="width: 15%" class="text-center">Qty</th>
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
                                        <button type="button" class="btn btn-outline-success btn-sm" id="btn-add-row">
                                            <i class="fas fa-plus"></i> Tambah Barang
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- RINGKASAN PEMBAYARAN (Tidak Berubah) --}}
                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th class="text-right" style="width: 50%">Subtotal:</th>
                                        <td class="text-right"><h5 class="font-weight-bold" id="label-subtotal">Rp 0</h5></td>
                                    </tr>
                                    <tr>
                                        <th class="align-middle text-right">Diskon:</th>
                                        <td>
                                            <div class="input-group input-group-sm mb-1">
                                                <input type="text" name="nama_diskon" class="form-control" placeholder="Keterangan Diskon">
                                            </div>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                                <input type="number" name="nilai_diskon" id="input-diskon" class="form-control text-right font-weight-bold text-danger" value="0" min="0">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-right align-middle">Pajak (PPN):</th>
                                        <td class="text-right">
                                            <div class="icheck-primary d-inline">
                                                <input type="checkbox" id="check-ppn" name="ppn_check" value="1">
                                                <label for="check-ppn" class="font-weight-normal">Kenakan PPN 11%</label>
                                            </div>
                                            <div id="label-ppn" class="text-muted small mt-1">Rp 0</div>
                                        </td>
                                    </tr>
                                    <tr class="border-top">
                                        <th class="text-right align-middle pt-3"><h4>Total Akhir:</h4></th>
                                        <td class="text-right pt-3"><h3 class="font-weight-bold text-success" id="label-grand-total">Rp 0</h3></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.penjualans.index') }}" class="btn btn-default">
                        <i class="fas fa-arrow-left mr-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm" id="btn-save" disabled>
                        <i class="fas fa-save mr-2"></i> Simpan Transaksi
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
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .table-valign-middle td { vertical-align: middle; }
</style>
@endpush

@push('js')
<script>
// ... (Bagian JS SAMA PERSIS dengan sebelumnya, tidak perlu diubah) ...
$(document).ready(function() {
    let rowIndex = 0;
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }
    function addRow() {
        rowIndex++;
        let row = `
            <tr id="row-${rowIndex}">
                <td>
                    <select name="items[${rowIndex}][barang_id]" class="form-control select-barang" data-row="${rowIndex}" required style="width: 100%;">
                        <option value="">Cari Barang...</option>
                    </select>
                </td>
                <td class="text-center align-middle"><span id="stock-${rowIndex}" class="badge badge-secondary">0</span></td>
                <td><input type="number" name="items[${rowIndex}][qty]" class="form-control text-center input-qty" data-row="${rowIndex}" value="1" min="1" required disabled></td>
                <td class="text-right align-middle"><span id="price-text-${rowIndex}">0</span><input type="hidden" id="price-input-${rowIndex}" value="0"></td>
                <td class="text-right align-middle font-weight-bold"><span id="subtotal-${rowIndex}">0</span></td>
                <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-xs btn-remove" data-row="${rowIndex}"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        $('#items-table tbody').append(row);
        initSelect2(rowIndex);
        calculateTotal();
    }
    function initSelect2(index) {
        $(`.select-barang[data-row="${index}"]`).select2({
            theme: 'bootstrap4',
            placeholder: 'Ketik Nama/Kode Part...',
            ajax: {
                url: "{{ route('admin.api.penjualan.items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term, lokasi_id: "{{ $lokasi->id }}" }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });
    }
    $(document).on('select2:select', '.select-barang', function(e) {
        let row = $(this).data('row');
        let data = e.params.data;
        let price = parseFloat(data.price) || 0;
        let stock = parseInt(data.stock) || 0;
        $(`#price-text-${row}`).text(formatRupiah(price));
        $(`#price-input-${row}`).val(price);
        let badgeClass = stock > 0 ? 'badge-info' : 'badge-danger';
        $(`#stock-${row}`).text(stock).attr('class', `badge ${badgeClass}`);
        let qtyInput = $(`.input-qty[data-row="${row}"]`);
        qtyInput.prop('disabled', false).attr('max', stock).val(1);
        if(stock <= 0) { qtyInput.prop('disabled', true).val(0); alert('Stok barang ini habis!'); }
        calculateRow(row);
    });
    $(document).on('input', '.input-qty', function() {
        let row = $(this).data('row');
        let max = parseInt($(this).attr('max'));
        let val = parseInt($(this).val());
        if(val > max) { alert('Jumlah melebihi stok tersedia!'); $(this).val(max); }
        if(val < 1) $(this).val(1);
        calculateRow(row);
    });
    function calculateRow(row) {
        let qty = parseInt($(`.input-qty[data-row="${row}"]`).val()) || 0;
        let price = parseFloat($(`#price-input-${row}`).val()) || 0;
        let subtotal = qty * price;
        $(`#subtotal-${row}`).text(formatRupiah(subtotal));
        calculateTotal();
    }
    $(document).on('click', '.btn-remove', function() {
        let row = $(this).data('row');
        $(`#row-${row}`).remove();
        calculateTotal();
    });
    function calculateTotal() {
        let subtotalGlobal = 0;
        $('.input-qty').each(function() {
            let row = $(this).data('row');
            let qty = parseInt($(this).val()) || 0;
            let price = parseFloat($(`#price-input-${row}`).val()) || 0;
            subtotalGlobal += (qty * price);
        });
        let diskon = parseFloat($('#input-diskon').val()) || 0;
        let isPpn = $('#check-ppn').is(':checked');
        if(diskon > subtotalGlobal) { diskon = subtotalGlobal; $('#input-diskon').val(diskon); }
        let dpp = subtotalGlobal - diskon;
        let ppnValue = isPpn ? (dpp * 0.11) : 0;
        let grandTotal = dpp + ppnValue;
        $('#label-subtotal').text(formatRupiah(subtotalGlobal));
        $('#label-ppn').text(isPpn ? formatRupiah(ppnValue) : 'Rp 0');
        $('#label-grand-total').text(formatRupiah(grandTotal));
        if(subtotalGlobal > 0) { $('#btn-save').prop('disabled', false); } else { $('#btn-save').prop('disabled', true); }
    }
    $('#btn-add-row').click(addRow);
    $('#input-diskon').on('input', calculateTotal);
    $('#check-ppn').on('change', calculateTotal);
    addRow();
});
</script>
@endpush