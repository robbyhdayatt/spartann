@extends('adminlte::page')

@section('title', 'Buat Request PO Multi-Dealer')
@section('plugins.Select2', true)

@section('content_header')
    <h1>Request Stok ke Pusat (Multi-Dealer)</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="po-form">
        @csrf
        <input type="hidden" name="requests" id="requests_json">

        <div class="card-body">
            {{-- Header Info --}}
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

            <hr>

            {{-- Area Input Dinamis --}}
            <div id="dealer-container">
                {{-- Card Dealer akan ditambahkan di sini oleh JS --}}
            </div>

            {{-- Tombol Tambah Dealer --}}
            <div class="text-center mt-4">
                <button type="button" class="btn btn-lg btn-outline-primary" id="btn-add-dealer">
                    <i class="fas fa-store"></i> Tambah Dealer Tujuan
                </button>
            </div>

        </div>
        <div class="card-footer text-right">
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Batal</a>
            <button type="button" id="btn-submit" class="btn btn-success">
                <i class="fas fa-save"></i> Proses Semua Request
            </button>
        </div>
    </form>
</div>

{{-- TEMPLATE: Card Dealer --}}
<template id="tpl-dealer-card">
    <div class="card card-secondary card-outline mb-3 dealer-card" data-id="{id}">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div style="width: 40%;">
                    <select class="form-control select2-dealer dealer-select">
                        <option value="">-- Pilih Dealer --</option>
                        @foreach($dealers as $dealer)
                            <option value="{{ $dealer->id }}">{{ $dealer->nama_lokasi }} ({{ $dealer->kode_lokasi }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-tool text-danger btn-remove-dealer" title="Hapus Dealer Ini">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-2">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th width="15%">Qty</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody class="item-list">
                    {{-- Item Rows Here --}}
                </tbody>
            </table>
            <button type="button" class="btn btn-xs btn-info btn-add-item"><i class="fas fa-plus"></i> Tambah Barang</button>
        </div>
    </div>
</template>

{{-- TEMPLATE: Item Row --}}
<template id="tpl-item-row">
    <tr class="item-row">
        <td>
            <select class="form-control form-control-sm select2-item item-select">
                <option value="">Pilih Barang</option>
                @foreach($barangs as $barang)
                    <option value="{{ $barang->id }}">
                        {{ $barang->part_name }} ({{ $barang->part_code }}) - Stok: {{ $barang->selling_out }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm item-qty" min="1" value="1" placeholder="Qty">
        </td>
        <td>
            <button type="button" class="btn btn-xs btn-danger btn-remove-item">&times;</button>
        </td>
    </tr>
</template>

@stop

@push('js')
<script>
$(document).ready(function() {
    let uniqueId = 0;

    // Tambah Dealer Baru
    $('#btn-add-dealer').click(function() {
        uniqueId++;
        let tpl = $('#tpl-dealer-card').html().replace(/{id}/g, uniqueId);
        $('#dealer-container').append(tpl);

        // Init Select2 untuk dealer baru
        let newCard = $('#dealer-container').find('.dealer-card').last();
        newCard.find('.select2-dealer').select2({ theme: 'bootstrap4', width: '100%' });

        // Otomatis tambah 1 baris item
        addItemRow(newCard);
    });

    // Hapus Dealer
    $(document).on('click', '.btn-remove-dealer', function() {
        if(confirm('Hapus dealer ini dari list?')) {
            $(this).closest('.dealer-card').remove();
        }
    });

    // Tambah Item di dalam Dealer
    $(document).on('click', '.btn-add-item', function() {
        let card = $(this).closest('.dealer-card');
        addItemRow(card);
    });

    function addItemRow(card) {
        let tpl = $('#tpl-item-row').html();
        let tbody = card.find('.item-list');
        tbody.append(tpl);

        // Init Select2 Item
        tbody.find('.item-row').last().find('.select2-item').select2({ theme: 'bootstrap4', width: '100%' });
    }

    // Hapus Item
    $(document).on('click', '.btn-remove-item', function() {
        $(this).closest('tr').remove();
    });

    // Submit Form Logic
    $('#btn-submit').click(function() {
        let payload = [];
        let isValid = true;

        $('.dealer-card').each(function() {
            let card = $(this);
            let dealerId = card.find('.dealer-select').val();
            let items = [];

            if (!dealerId) {
                alert('Silakan pilih dealer tujuan.');
                isValid = false;
                return false;
            }

            card.find('.item-row').each(function() {
                let row = $(this);
                let barangId = row.find('.item-select').val();
                let qty = row.find('.item-qty').val();

                if(barangId && qty > 0) {
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
            alert('Mohon isi minimal satu dealer dan satu barang.');
            return;
        }

        // Masukkan JSON ke input hidden
        $('#requests_json').val(JSON.stringify(payload));

        // Submit Form
        $('#po-form').submit();
    });

    // Trigger klik tambah dealer pertama kali load
    $('#btn-add-dealer').click();
});
</script>
<style>
    .dealer-card { border-left: 5px solid #007bff; }
</style>
@endpush
