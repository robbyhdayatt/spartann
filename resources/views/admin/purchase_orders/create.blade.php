@extends('adminlte::page')

@section('title', 'Buat Purchase Order Baru')

@section('content_header')
    <h1>Buat Purchase Order Baru</h1>
@stop

@section('content')
    <div class="card">
        <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="po-form">
            @csrf
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{-- PO Header --}}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Tanggal PO</label>
                        <input type="date" class="form-control" name="tanggal_po" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Supplier</label>
                        <select class="form-control select2" id="supplier-select" name="supplier_id" required style="width: 100%;">
                            <option value="" disabled selected>Pilih Supplier</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->nama_supplier }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Tujuan lokasi</label>
                            <input type="text" class="form-control" value="{{ $lokasiPusat->nama_lokasi }}" readonly>
                            <input type="hidden" name="lokasi_id" value="{{ $lokasiPusat->id }}">
                    </div>
                </div>
                 <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2"></textarea>
                </div>

                {{-- PO Items Table --}}
                <h5 class="mt-4">Item Sparepart</h5>
                <hr>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th style="width: 120px">Qty</th>
                                <th style="width: 200px">Harga Beli</th>
                                <th style="width: 200px">Subtotal</th>
                                <th style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-table">
                            {{-- Items will be added here by JavaScript --}}
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="add-item-btn" disabled>+ Tambah Item</button>

                {{-- Total Calculation --}}
                <div class="row justify-content-end mt-4">
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tr>
                                <th>Subtotal</th>
                                <td class="text-right" id="display-subtotal">Rp 0</td>
                            </tr>
                            {{-- ++ PERUBAHAN: Baris PPN dihapus ++ --}}
                            <tr>
                                <th style="font-size: 1.2rem;">Grand Total</th>
                                <td class="text-right font-weight-bold" style="font-size: 1.2rem;" id="display-grand-total">Rp 0</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Simpan Purchase Order</button>
                 <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    {{-- Hidden template for a new row --}}
    <template id="po-item-template">
        <tr>
            <td>
                <select class="form-control item-part" name="items[__INDEX__][part_id]" required style="width: 100%;">
                    <option value="" disabled selected>Pilih Part</option>
                    @foreach($parts as $part)
                        {{-- ++ PERUBAHAN: Tambahkan data-harga pada option ++ --}}
                        <option value="{{ $part->id }}" data-harga="{{ $part->harga_satuan }}">{{ $part->nama_part }} ({{ $part->kode_part }})</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" class="form-control item-qty" name="items[__INDEX__][qty]" min="1" value="1" required></td>
            <td>
                <input type="text" class="form-control item-harga text-right" readonly>
            </td>
            <td><input type="text" class="form-control item-subtotal text-right" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">&times;</button></td>
        </tr>
    </template>
@stop

@section('plugins.Select2', true)

@push('css')
<style>
    .select2-container .select2-selection--single { height: calc(2.25rem + 2px) !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5 !important; padding-left: .75rem !important; padding-top: .375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px) !important; }
</style>
@endpush

@section('js')
    <script>
        $(document).ready(function() {
            $('.select2').select2({ placeholder: "Pilih Opsi" });

            let itemIndex = 0;
            const addItemBtn = $('#add-item-btn');
            const supplierSelect = $('#supplier-select');
            const itemsTable = $('#po-items-table');

            const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

            supplierSelect.on('change', function() {
                if ($(this).val()) {
                    addItemBtn.prop('disabled', false);
                    itemsTable.empty();
                    calculateAll();
                } else {
                    addItemBtn.prop('disabled', true);
                }
            });

            addItemBtn.on('click', function() {
                let template = $('#po-item-template').html().replace(/__INDEX__/g, itemIndex);
                itemsTable.append(template);
                itemsTable.find('tr').last().find('.item-part').select2({ placeholder: "Pilih Part" });
                itemIndex++;
            });

            itemsTable.on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                calculateAll();
            });

            // ++ PERUBAHAN UTAMA: Logika AJAX diganti, harga diambil dari data-attribute ++
            itemsTable.on('change', '.item-part', function() {
                let row = $(this).closest('tr');
                let selectedOption = $(this).find('option:selected');
                let harga = parseFloat(selectedOption.data('harga')) || 0;

                row.find('.item-harga').val(formatRupiah(harga));
                updateSubtotal(row);
            });

            itemsTable.on('keyup change', '.item-qty', function() {
                updateSubtotal($(this).closest('tr'));
            });

            function updateSubtotal(row) {
                let hargaText = row.find('.item-harga').val().replace(/[^0-9,-]+/g,"").replace(',','.');
                let harga = parseFloat(hargaText) || 0;
                let qty = parseInt(row.find('.item-qty').val()) || 0;
                row.find('.item-subtotal').val(formatRupiah(qty * harga));
                calculateAll();
            }

            // ++ PERUBAHAN: Fungsi kalkulasi disederhanakan (tanpa PPN) ++
            function calculateAll() {
                let subtotalTotal = 0;
                itemsTable.find('tr').each(function() {
                    let subtotalText = $(this).find('.item-subtotal').val().replace(/[^0-9,-]+/g,"").replace(',','.');
                    subtotalTotal += parseFloat(subtotalText) || 0;
                });

                $('#display-subtotal').text(formatRupiah(subtotalTotal));
                // Grand total sama dengan subtotal
                $('#display-grand-total').text(formatRupiah(subtotalTotal));
            }
        });
    </script>
@stop
