@extends('adminlte::page')

@section('title', 'Buat Transaksi PO')
@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Transaksi PO</h1>
@stop

@section('content')
<div class="card card-primary card-outline card-tabs">
    <div class="card-header p-0 pt-1 border-bottom-0">
        <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="mode-dealer-tab" data-toggle="pill" href="#mode-dealer" role="tab" onclick="setMode('dealer_request')">
                    <i class="fas fa-store"></i> Request Dealer ke Pusat
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="mode-supplier-tab" data-toggle="pill" href="#mode-supplier" role="tab" onclick="setMode('supplier_po')">
                    <i class="fas fa-truck"></i> Gudang Order ke Supplier
                </a>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="po-form">
            @csrf
            {{-- Input Hidden untuk Tipe PO --}}
            <input type="hidden" name="po_type" id="po_type" value="dealer_request">
            <input type="hidden" name="requests" id="requests_json"> {{-- Untuk Dealer --}}
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" class="form-control" name="tanggal_po" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                
                {{-- Form Khusus Supplier --}}
                <div class="col-md-4 supplier-field" style="display: none;">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select class="form-control select2" name="supplier_id" id="supplier_select" style="width: 100%;">
                            <option value="">-- Pilih Supplier --</option>
                            @foreach(\App\Models\Supplier::all() as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->nama_supplier }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Catatan</label>
                        <input type="text" class="form-control" name="catatan" placeholder="Opsional...">
                    </div>
                </div>
            </div>

            <hr>

            {{-- AREA 1: INPUT UNTUK DEALER REQUEST (Multi Dealer) --}}
            <div id="area-dealer-request">
                <div class="alert alert-info py-1"><i class="fas fa-info-circle"></i> Mode ini digunakan Service MD untuk meminta stok ke Gudang Pusat.</div>
                <div id="dealer-container"></div>
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-dealer">
                        <i class="fas fa-plus"></i> Tambah Dealer Tujuan
                    </button>
                </div>
            </div>

            {{-- AREA 2: INPUT UNTUK SUPPLIER PO (Single List) --}}
            <div id="area-supplier-po" style="display: none;">
                <div class="alert alert-warning py-1"><i class="fas fa-info-circle"></i> Mode ini digunakan Admin Gudang untuk membeli stok dari Supplier.</div>
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr class="bg-light">
                            <th>Barang</th>
                            <th width="15%">Qty</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody id="supplier-items-body">
                        {{-- Row Dinamis --}}
                    </tbody>
                </table>
                <button type="button" class="btn btn-info btn-xs" id="btn-add-supplier-item"><i class="fas fa-plus"></i> Tambah Barang</button>
            </div>

            <div class="mt-4 text-right">
                <button type="button" id="btn-submit" class="btn btn-success btn-lg"><i class="fas fa-paper-plane"></i> Proses Transaksi</button>
            </div>
        </form>
    </div>
</div>

{{-- TEMPLATE: Dealer Card (Sama seperti sebelumnya) --}}
<template id="tpl-dealer-card">
    <div class="card card-secondary card-outline mb-2 dealer-card">
        <div class="card-body p-2">
            <div class="d-flex justify-content-between mb-2">
                <select class="form-control select2-dealer dealer-select" style="width: 40%;">
                    <option value="">-- Pilih Dealer --</option>
                    @foreach($dealers as $dealer)
                        <option value="{{ $dealer->id }}">{{ $dealer->nama_lokasi }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-tool text-danger btn-remove-dealer"><i class="fas fa-times"></i></button>
            </div>
            <table class="table table-sm table-borderless mb-0">
                <tbody class="item-list"></tbody>
            </table>
            <button type="button" class="btn btn-xs btn-light border btn-add-item">Tambah Item</button>
        </div>
    </div>
</template>

{{-- TEMPLATE: Item Row (Bisa dipakai keduanya) --}}
<template id="tpl-item-row">
    <tr class="item-row">
        <td>
            <select class="form-control form-control-sm select2-item item-select" name="items[{idx}][barang_id]" style="width: 100%;">
                <option value="">Pilih Barang</option>
                @foreach($barangs as $barang)
                    <option value="{{ $barang->id }}">{{ $barang->part_name }} ({{ $barang->part_code }})</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm item-qty" name="items[{idx}][qty]" placeholder="Qty" min="1"></td>
        <td><button type="button" class="btn btn-xs btn-danger btn-remove-item">&times;</button></td>
    </tr>
</template>

@stop

@push('js')
<script>
    let mode = 'dealer_request';
    let itemIdx = 0;

    function setMode(newMode) {
        mode = newMode;
        $('#po_type').val(mode);
        if(mode === 'dealer_request') {
            $('#area-dealer-request').show();
            $('#area-supplier-po').hide();
            $('.supplier-field').hide();
        } else {
            $('#area-dealer-request').hide();
            $('#area-supplier-po').show();
            $('.supplier-field').show();
        }
    }

    $(document).ready(function() {
        // --- LOGIC DEALER REQUEST ---
        $('#btn-add-dealer').click(function() {
            let tpl = $('#tpl-dealer-card').html();
            $('#dealer-container').append(tpl);
            let card = $('#dealer-container').find('.dealer-card').last();
            card.find('.select2-dealer').select2({theme: 'bootstrap4'});
            addItemRow(card.find('.item-list')); 
        });

        $(document).on('click', '.btn-add-item', function() {
            addItemRow($(this).closest('.card-body').find('.item-list'));
        });

        // --- LOGIC SUPPLIER PO ---
        $('#btn-add-supplier-item').click(function() {
            addItemRow($('#supplier-items-body'), true);
        });

        function addItemRow(targetContainer, isSupplier = false) {
            itemIdx++;
            let tpl = $('#tpl-item-row').html().replace(/{idx}/g, itemIdx);
            targetContainer.append(tpl);
            targetContainer.find('.select2-item').last().select2({theme: 'bootstrap4'});
        }

        $(document).on('click', '.btn-remove-item', function() { $(this).closest('tr').remove(); });
        $(document).on('click', '.btn-remove-dealer', function() { $(this).closest('.dealer-card').remove(); });

        // --- SUBMIT HANDLER ---
        $('#btn-submit').click(function() {
            if(mode === 'dealer_request') {
                // Logic khusus build JSON untuk dealer request (sama seperti kode sebelumnya)
                let payload = [];
                $('.dealer-card').each(function() {
                    let dealerId = $(this).find('.dealer-select').val();
                    let items = [];
                    $(this).find('.item-row').each(function() {
                        let id = $(this).find('.item-select').val();
                        let q = $(this).find('.item-qty').val();
                        if(id && q) items.push({barang_id: id, qty: q});
                    });
                    if(dealerId && items.length) payload.push({lokasi_id: dealerId, items: items});
                });
                $('#requests_json').val(JSON.stringify(payload));
            } 
            // Jika supplier_po, form akan submit name="items[idx][...]" secara native HTML
            $('#po-form').submit();
        });

        // Init pertama
        $('#btn-add-dealer').click(); 
    });
</script>
@endpush