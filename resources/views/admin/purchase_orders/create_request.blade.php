@extends('adminlte::page')

@section('title', 'Buat Request ke Pusat')
@section('plugins.Select2', true)

@section('content_header')
    <h1><i class="fas fa-store"></i> Request Stok ke Pusat</h1>
@stop

@section('content')
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">Form Permintaan Barang</h3>
    </div>
    <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="po-form">
        @csrf
        <input type="hidden" name="po_type" value="dealer_request">
        <input type="hidden" name="requests" id="requests_json">

        <div class="card-body">
            {{-- Header Informasi --}}
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Tanggal Request</label>
                    <input type="date" class="form-control" name="tanggal_po" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-6 form-group">
                    <label>Sumber Barang (Pusat)</label>
                    <input type="text" class="form-control" value="{{ $sumberPusat->nama_lokasi ?? 'Gudang Pusat' }}" readonly>
                    <input type="hidden" name="sumber_lokasi_id" value="{{ $sumberPusat->id ?? 1 }}">
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Silahkan pilih Dealer tujuan dan barang yang ingin direquest.
            </div>

            {{-- Container Dealer --}}
            <div id="dealer-container"></div>

            {{-- Tombol Tambah Dealer --}}
            <div class="text-center mt-4 mb-3">
                <button type="button" class="btn btn-outline-primary btn-lg" id="btn-add-dealer">
                    <i class="fas fa-plus-circle"></i> Tambah Dealer Tujuan
                </button>
            </div>
        </div>

        <div class="card-footer text-right">
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Batal</a>
            <button type="button" id="btn-submit" class="btn btn-success btn-lg ml-2"><i class="fas fa-save"></i> Kirim Request</button>
        </div>
    </form>
</div>

{{-- TEMPLATE: Card Dealer --}}
<template id="tpl-dealer-card">
    <div class="card card-secondary card-outline mb-4 dealer-card shadow-sm">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="width: 70%;">
                    <h5 class="m-0 mr-3 text-dark"><i class="fas fa-building text-primary"></i> Tujuan:</h5>
                    <select class="form-control select2-dealer dealer-select" style="width: 60%;">
                        <option value="">-- Pilih Dealer --</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}">{{ $dealer->nama_lokasi }} ({{ $dealer->kode_lokasi }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-tool btn-lg text-danger btn-remove-dealer" title="Hapus Dealer">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered table-striped mb-0">
                <thead class="bg-secondary text-white">
                    <tr>
                        <th style="width: 60%; vertical-align: middle;">Nama Barang</th>
                        <th style="width: 25%; vertical-align: middle;">Qty Request</th>
                        <th style="width: 15%; text-align: center; vertical-align: middle;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="item-list">
                    {{-- Item Rows akan masuk disini --}}
                </tbody>
            </table>
            <div class="p-3 text-center bg-light border-top">
                <button type="button" class="btn btn-info btn-sm px-4 btn-add-item">
                    <i class="fas fa-plus"></i> Tambah Baris Barang
                </button>
            </div>
        </div>
    </div>
</template>

{{-- TEMPLATE: Item Row --}}
<template id="tpl-item-row">
    <tr class="item-row">
        <td class="p-2 align-middle">
            <select class="form-control select2-item item-select" style="width: 100%;">
                <option value="">-- Pilih Barang --</option>
                @foreach($barangs as $barang)
                    <option value="{{ $barang->id }}">
                        {{ $barang->part_name }} ({{ $barang->part_code }})
                    </option>
                @endforeach
            </select>
        </td>
        <td class="p-2 align-middle">
            <div class="input-group">
                <input type="number" class="form-control item-qty text-center font-weight-bold" placeholder="0" min="1" style="font-size: 1.1rem;">
                <div class="input-group-append">
                    <span class="input-group-text">Pcs</span>
                </div>
            </div>
        </td>
        <td class="p-2 text-center align-middle">
            <button type="button" class="btn btn-danger btn-remove-item px-3" title="Hapus Baris">
                <i class="fas fa-trash-alt fa-lg"></i>
            </button>
        </td>
    </tr>
</template>

<style>
    /* Styling tambahan agar input qty terlihat pas */
    .table td { vertical-align: middle; }
    .input-group-text { background-color: #f4f6f9; }
    
    /* Select2 Tweaks agar tinggi sama dengan input qty */
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        padding-top: 4px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 4px !important;
    }
</style>
@stop

@push('js')
<script>
$(document).ready(function() {
    
    // 1. Tambah Dealer Baru
    $('#btn-add-dealer').click(function() {
        let tpl = $('#tpl-dealer-card').html();
        $('#dealer-container').append(tpl);
        
        let newCard = $('#dealer-container').find('.dealer-card').last();
        
        // Init Select2 Dealer
        newCard.find('.select2-dealer').select2({
            theme: 'bootstrap4',
            placeholder: "Pilih Dealer Tujuan"
        });

        // Tambah 1 baris item otomatis
        addItemRow(newCard.find('.item-list'));
    });

    // 2. Tambah Baris Item
    $(document).on('click', '.btn-add-item', function() {
        addItemRow($(this).closest('.dealer-card').find('.item-list'));
    });

    function addItemRow(tbody) {
        let tpl = $('#tpl-item-row').html();
        let newRow = $(tpl);
        tbody.append(newRow);

        // Init Select2 Barang
        let selectItem = newRow.find('.select2-item');
        selectItem.select2({
            theme: 'bootstrap4',
            placeholder: "Cari Barang (Nama / Kode)"
        });

        // EVENT LOGIC: Mencegah duplikasi barang di Dealer yang sama
        selectItem.on('select2:opening', function (e) {
            disableSelectedItems($(this));
        });
    }

    // 3. LOGIKA CEGAH DUPLIKASI (Core Function)
    function disableSelectedItems(currentSelect) {
        // Cari container dealer (scope)
        let dealerCard = currentSelect.closest('.dealer-card');
        
        // Ambil semua value yang SUDAH dipilih di card ini (kecuali diri sendiri)
        let selectedValues = [];
        dealerCard.find('.item-select').each(function() {
            if (!$(this).is(currentSelect)) { // Jangan masukkan nilai saya sendiri
                let val = $(this).val();
                if (val) selectedValues.push(val);
            }
        });

        // Loop semua option di select ini
        currentSelect.find('option').each(function() {
            let optionVal = $(this).val();
            
            // Jika value option ada di array selectedValues, disable!
            if (selectedValues.includes(optionVal)) {
                $(this).prop('disabled', true);
            } else {
                $(this).prop('disabled', false);
            }
        });
    }

    // 4. Hapus Baris Item
    $(document).on('click', '.btn-remove-item', function() {
        let tbody = $(this).closest('tbody');
        // Jika sisa 1 baris, jangan dihapus tapi kosongkan value (opsional, disini saya allow hapus)
        $(this).closest('tr').remove();
    });

    // 5. Hapus Dealer
    $(document).on('click', '.btn-remove-dealer', function() {
        if(confirm('Hapus seluruh request untuk dealer ini?')) {
            $(this).closest('.dealer-card').remove();
        }
    });

    // 6. SUBMIT FORM
    $('#btn-submit').click(function() {
        let payload = [];
        let isValid = true;

        $('.dealer-card').each(function() {
            let card = $(this);
            let dealerId = card.find('.dealer-select').val();
            let items = [];

            if (!dealerId) {
                alert('Mohon pilih Dealer Tujuan pada setiap blok.');
                isValid = false;
                return false; 
            }

            card.find('.item-row').each(function() {
                let row = $(this);
                let barangId = row.find('.item-select').val();
                let qty = row.find('.item-qty').val();

                if (barangId && qty > 0) {
                    items.push({
                        barang_id: barangId,
                        qty: qty
                    });
                }
            });

            if (items.length > 0) {
                payload.push({
                    lokasi_id: dealerId,
                    items: items
                });
            }
        });

        if (!isValid) return;

        if (payload.length === 0) {
            alert('Mohon isi minimal satu barang dan qty yang valid.');
            return;
        }

        // Masukkan JSON ke hidden input
        $('#requests_json').val(JSON.stringify(payload));
        $('#po-form').submit();
    });

    // Init: Trigger tambah dealer pertama kali load
    $('#btn-add-dealer').click(); 
});
</script>
@endpush