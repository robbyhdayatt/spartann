@extends('adminlte::page')

@section('title', 'Buat Penjualan Baru')

@section('content_header')
    <h1>Buat Penjualan Baru (POS)</h1>
@stop

@section('content')
    <div class="card">
        <form action="{{ route('admin.penjualans.store') }}" method="POST" id="sales-form">
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

                {{-- Header Penjualan --}}
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Tanggal Penjualan</label>
                        <input type="date" class="form-control" name="tanggal_jual" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    
                    {{-- PERUBAHAN: Input Manual Nama Pelanggan --}}
                    <div class="col-md-4 form-group">
                        <label>Nama Pelanggan</label>
                        <input type="text" class="form-control" name="customer_name" placeholder="Ketik Nama Pelanggan (cth: Budi)" required>
                    </div>
                    {{-- AKHIR PERUBAHAN --}}

                    <div class="col-md-4 form-group">
                        <label>Lokasi Stok (Sumber)</label>
                        <input type="text" class="form-control" value="{{ $lokasi->nama_lokasi }}" readonly>
                    </div>
                </div>

                {{-- Items Table --}}
                <h5 class="mt-4">Item Barang</h5>
                <hr>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th style="width: 100px">Stok</th>
                                <th style="width: 150px">Harga (Rp)</th>
                                <th style="width: 100px">Qty</th>
                                <th style="width: 200px">Subtotal</th>
                                <th style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody id="sales-items-table">
                            {{-- Items will be added here by JavaScript --}}
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="add-item-btn">
                    <i class="fas fa-plus"></i> Tambah Item
                </button>

                {{-- Total Calculation --}}
                <div class="row justify-content-end mt-4">
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tr>
                                <th style="font-size: 1.2rem;">Grand Total</th>
                                <td class="text-right font-weight-bold" style="font-size: 1.2rem;" id="display-grand-total">Rp 0</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
                <a href="{{ route('admin.penjualans.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    {{-- Template Row (Hidden) --}}
    <template id="sales-item-template">
        <tr>
            <td>
                <select class="form-control item-select" name="items[{index}][barang_id]" required style="width: 100%;">
                    <option value="">Cari Barang...</option>
                </select>
            </td>
            <td>
                <input type="text" class="form-control item-stok" readonly tabindex="-1" style="background-color: #f4f6f9;">
            </td>
            <td>
                <input type="text" class="form-control item-price text-right" readonly tabindex="-1">
            </td>
            <td>
                <input type="number" class="form-control item-qty" name="items[{index}][qty]" min="1" value="1" required>
            </td>
            <td>
                <input type="text" class="form-control item-subtotal text-right" readonly tabindex="-1">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-item-btn">&times;</button>
            </td>
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
            // HAPUS: $('#konsumen-select').select2(...) karena elemennya sudah diganti input text biasa

            let itemIndex = 0;
            let productList = []; 
            const itemsTable = $('#sales-items-table');
            const displayGrandTotal = $('#display-grand-total');

            // Format Rupiah
            const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

            // Load data barang via API saat halaman dibuka
            let currentLokasiId = "{{ $lokasi->id ?? '' }}";

            $.getJSON("{{ route('admin.api.get-barang-items') }}", { lokasi_id: currentLokasiId }, function(data) {
                productList = data;
                addItemRow();
            }).fail(function() {
                console.error("Gagal memuat barang");
            });

            // Fungsi Tambah Baris
            function addItemRow() {
                let template = $('#sales-item-template').html().replace(/{index}/g, itemIndex);
                itemsTable.append(template);

                let lastRow = itemsTable.find('tr').last();
                let select = lastRow.find('.item-select');

                productList.forEach(p => {
                    let label = `${p.part_name} (${p.part_code})`;
                    if(p.merk) label += ` - ${p.merk}`;
                    select.append(new Option(label, p.id));
                });

                select.select2({
                    theme: 'bootstrap4',
                    placeholder: "Pilih Barang",
                    width: '100%'
                });

                itemIndex++;
            }

            $('#add-item-btn').on('click', function() {
                addItemRow();
            });

            itemsTable.on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                calculateAll();
            });

            itemsTable.on('change', '.item-select', function() {
                let row = $(this).closest('tr');
                let id = $(this).val();
                let item = productList.find(p => p.id == id);

                if(item) {
                    row.find('.item-stok').val(item.total_stok);
                    row.find('.item-price').val(formatRupiah(item.retail));

                    let qtyInput = row.find('.item-qty');
                    qtyInput.attr('max', item.total_stok);

                    if(parseInt(qtyInput.val()) > item.total_stok) {
                        qtyInput.val(1);
                    }
                    calculateRow(row, item.retail);
                }
            });

            itemsTable.on('input change', '.item-qty', function() {
                let row = $(this).closest('tr');
                let id = row.find('.item-select').val();
                let item = productList.find(p => p.id == id);

                if(item) {
                    calculateRow(row, item.retail);
                }
            });

            function calculateRow(row, price) {
                let qty = parseInt(row.find('.item-qty').val()) || 0;
                let sub = qty * price;
                row.find('.item-subtotal').val(formatRupiah(sub));
                calculateAll();
            }

            function calculateAll() {
                let grandTotal = 0;
                itemsTable.find('tr').each(function() {
                    let subText = $(this).find('.item-subtotal').val();
                    let cleanValue = subText.replace(/[^\d]/g, ''); 
                    grandTotal += parseInt(cleanValue) || 0;
                });
                displayGrandTotal.text(formatRupiah(grandTotal));
            }
        });
    </script>
@stop