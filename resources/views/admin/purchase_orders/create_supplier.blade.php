@extends('adminlte::page')

@section('title', 'Order ke Supplier')
@section('plugins.Select2', true)

@section('content_header')
    <h1><i class="fas fa-truck"></i> Order ke Supplier</h1>
@stop

@section('content')
<div class="card card-warning card-outline">
    <div class="card-header">
        <h3 class="card-title">Form Purchase Order Supplier</h3>
    </div>
    <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="po-form">
        @csrf
        <input type="hidden" name="po_type" value="supplier_po">

        <div class="card-body">
            {{-- Header Input --}}
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Tanggal PO</label>
                    <input type="date" class="form-control" name="tanggal_po" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4 form-group">
                    <label>Pilih Supplier</label>
                    <select class="form-control select2" name="supplier_id" required style="width: 100%;">
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->nama_supplier }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Catatan</label>
                    <input type="text" class="form-control" name="catatan" placeholder="Nomor Referensi / Keterangan...">
                </div>
            </div>

            <hr>

            {{-- Tabel Input Item --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="bg-secondary text-white">
                        <tr>
                            <th style="width: 60%; vertical-align: middle;">Nama Barang</th>
                            <th style="width: 25%; vertical-align: middle;">Qty Order</th>
                            <th style="width: 15%; text-align: center; vertical-align: middle;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        {{-- Baris item akan ditambahkan disini via JS --}}
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-info btn-sm px-3" id="btn-add-item">
                    <i class="fas fa-plus"></i> Tambah Baris Barang
                </button>
            </div>
        </div>

        <div class="card-footer text-right">
            <a href="{{ route('admin.purchase-orders.index', ['type' => 'supplier_po']) }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-warning ml-2"><i class="fas fa-paper-plane"></i> Buat PO</button>
        </div>
    </form>
</div>

{{-- TEMPLATE ROW --}}
<template id="tpl-row">
    <tr class="item-row">
        <td class="p-2 align-middle">
            <select class="form-control select2-item item-select" name="items[{idx}][barang_id]" required style="width: 100%;">
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
                <input type="number" class="form-control item-qty text-center font-weight-bold" name="items[{idx}][qty]" placeholder="0" required min="1" style="font-size: 1.1rem;">
                <div class="input-group-append">
                    <span class="input-group-text">Pcs</span>
                </div>
            </div>
        </td>
        <td class="p-2 text-center align-middle">
            <button type="button" class="btn btn-danger btn-remove px-3" title="Hapus Baris">
                <i class="fas fa-trash-alt fa-lg"></i>
            </button>
        </td>
    </tr>
</template>

<style>
    /* Styling tambahan agar Select2 tingginya pas dengan Input Group */
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
    let idx = 0;

    // 1. Tambah Baris
    $('#btn-add-item').click(function() {
        idx++;
        let tpl = $('#tpl-row').html().replace(/{idx}/g, idx);
        let newRow = $(tpl);
        
        $('#items-body').append(newRow);
        
        // Init Select2 pada baris baru
        let selectItem = newRow.find('.select2-item');
        selectItem.select2({
            theme: 'bootstrap4',
            placeholder: "Cari Barang..."
        });

        // Event: Saat dropdown dibuka, cek barang yg sudah dipilih baris lain
        selectItem.on('select2:opening', function (e) {
            disableSelectedItems($(this));
        });
    });

    // 2. Logika Pencegah Duplikasi
    function disableSelectedItems(currentSelect) {
        // Ambil semua value dari select lain di dalam tabel ini
        let selectedValues = [];
        
        $('#items-body .item-select').each(function() {
            if (!$(this).is(currentSelect)) { // Kecuali diri sendiri
                let val = $(this).val();
                if (val) selectedValues.push(val);
            }
        });

        // Disable option yang sudah terpilih
        currentSelect.find('option').each(function() {
            let optionVal = $(this).val();
            if (selectedValues.includes(optionVal)) {
                $(this).prop('disabled', true);
            } else {
                $(this).prop('disabled', false);
            }
        });
    }

    // 3. Hapus Baris
    $(document).on('click', '.btn-remove', function() {
        $(this).closest('tr').remove();
    });

    // Init: Tambah 1 baris kosong saat load
    $('#btn-add-item').click();
});
</script>
@endpush