@extends('adminlte::page')

@section('title', 'Edit Service ' . $service->invoice_no)

@section('plugins.Select2', true)

@section('content_header')
    <h1>Edit Service: {{ $service->invoice_no }}</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        {{-- Box Informasi Service --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Detail Transaksi</h3></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><strong>No. Invoice:</strong> {{ $service->invoice_no }}</div>
                    <div class="col-md-4"><strong>Pelanggan:</strong> {{ $service->customer_name }}</div>
                    <div class="col-md-4"><strong>Plat No:</strong> {{ $service->plate_no }}</div>
                    {{-- Input hidden untuk menyimpan ID lokasi service --}}
                    <input type="hidden" id="service_lokasi_id" value="{{ optional($service->lokasi)->id }}">
                </div>
            </div>
        </div>

        {{-- Box Tambah Part --}}
        @can('manage-service')
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Tambah Pembelian Part Baru</h3></div>
            <form action="{{ route('admin.services.update', $service) }}" method="POST" id="edit-service-form">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    {{-- ++ PERUBAHAN 1: TATA LETAK HTML ++ --}}
                    <div class="row align-items-end">
                        <div class="col-md-4 form-group">
                            <label for="part-selector">Cari Part</label>
                            <select id="part-selector" class="form-control"></select>
                            {{-- Input hidden untuk menyimpan harga asli --}}
                            <input type="hidden" id="original-price" value="0">
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="qty-selector">Jumlah</label>
                            <div class="input-group">
                                <input type="number" id="qty-selector" class="form-control" min="1" value="1">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="stock-info">Stok: -</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="discount-selector">Diskon (%)</label>
                            <div class="input-group">
                                <input type="number" id="discount-selector" class="form-control" min="0" max="100" value="0">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="price-selector">Harga Satuan</label>
                            {{-- Ubah type ke "text" dan tambahkan readonly --}}
                            <input type="text" id="price-selector" class="form-control text-right" readonly>
                        </div>
                        <div class="col-md-2 form-group">
                            <button type="button" class="btn btn-primary" id="add-part-btn">Tambahkan</button>
                        </div>
                    </div>

                    <hr>
                    <h5>Item Part Baru</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th style="width: 15%;">Qty</th>
                                <th style="width: 20%;">Harga Satuan</th>
                                <th style="width: 20%;">Subtotal</th>
                                <th style="width: 5%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="parts-container">
                            {{-- Baris part baru akan ditambahkan di sini oleh JavaScript --}}
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                    <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
        @endcan

        {{-- Box Detail Item yang Sudah Ada --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Detail Item Awal</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Kode</th>
                            <th>Nama Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($service->details as $detail)
                            <tr>
                                <td><span class="badge badge-secondary">{{ $detail->item_category }}</span></td>
                                <td>{{ $detail->item_code ?? '-' }}</td>
                                <td>{{ $detail->item_name }}</td>
                                <td class="text-center">{{ $detail->quantity }}</td>
                                <td class="text-right">@rupiah($detail->price)</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Tidak ada item.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

{{-- ++ PERUBAHAN 2: CSS (Style untuk input readonly) ++ --}}
@push('css')
<style>
    /* Membuat input readonly tetap terlihat jelas (latar putih) */
    input[readonly].form-control {
        background-color: #fff;
        opacity: 1;
    }
</style>
@endpush

@push('js')
{{-- ++ PERUBAHAN 3: LOGIKA JAVASCRIPT ++ --}}
<script>
$(document).ready(function() {
    let itemIndex = 0;

    // --- Helper Functions ---
    /**
     * Memformat angka menjadi string Rupiah (Rp 123.456)
     */
    const formatAsRupiah = (number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0, // Hapus desimal ,00
            maximumFractionDigits: 0  // Hapus desimal ,00
        }).format(number);
    }

    /**
     * Mengubah string Rupiah (Rp 123.456) kembali menjadi angka (123456)
     */
    const parseRupiah = (text) => {
        if (!text) return 0;
        return parseFloat(text.replace(/[^0-9]/g, '')) || 0;
    }

    /**
     * Logika utama untuk menghitung harga setelah diskon
     */
    function calculateFinalPrice() {
        let originalPrice = parseFloat($('#original-price').val()) || 0;
        let diskonPersen = parseFloat($('#discount-selector').val()) || 0;

        if (diskonPersen < 0) diskonPersen = 0;
        if (diskonPersen > 100) diskonPersen = 100;

        let hargaSetelahDiskon = originalPrice * (1 - (diskonPersen / 100));

        // Atur nilai input harga satuan dengan format Rupiah
        $('#price-selector').val(formatAsRupiah(hargaSetelahDiskon));
    }

    // --- Event Listeners ---
    $('#part-selector').select2({
        theme: 'bootstrap4',
        placeholder: 'Ketik kode atau nama part...',
        ajax: {
            url: "{{ route('admin.parts.search') }}",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    lokasi_id: $('#service_lokasi_id').val()
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        var data = e.params.data;

        // Simpan harga asli ke input hidden
        $('#original-price').val(data.harga_satuan || 0);
        // Reset diskon ke 0
        $('#discount-selector').val(0);
        // Hitung dan format harga final
        calculateFinalPrice();

        $('#stock-info').text(`Stok: ${data.total_stock}`);
        $('#qty-selector').attr('max', data.total_stock);
    });

    // Hitung ulang harga jika diskon diubah
    $('#discount-selector').on('keyup change input', function() {
        calculateFinalPrice();
    });

    // Tombol Tambah Part
    $('#add-part-btn').on('click', function() {
        let selectedPart = $('#part-selector').select2('data')[0];
        let qty = $('#qty-selector').val();
        // Ambil harga final (setelah diskon) dari input yang sudah diformat
        let price = parseRupiah($('#price-selector').val());
        let priceDisplay = $('#price-selector').val(); // "Rp 123.456"

        if (!selectedPart || !qty || qty <= 0 || price < 0) {
            alert('Silakan pilih Part dan isi jumlah yang valid.');
            return;
        }

        let subtotal = qty * price;
        let rowHtml = `
            <tr class="new-item-row">
                <td>
                    ${selectedPart.text}
                    <input type="hidden" name="items[${itemIndex}][part_id]" value="${selectedPart.id}">
                    <input type="hidden" name="items[${itemIndex}][price]" value="${price}">
                    <input type="hidden" name="items[${itemIndex}][quantity]" value="${qty}">
                </td>
                <td>${qty}</td>
                <td class="text-right">${priceDisplay}</td>
                <td class="text-right">${formatAsRupiah(subtotal)}</td>
                <td><button type="button" class="btn btn-xs btn-danger remove-item-btn"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;

        $('#parts-container').append(rowHtml);
        itemIndex++;

        // Reset input
        $('#part-selector').val(null).trigger('change');
        $('#qty-selector').val(1);
        $('#discount-selector').val(0);
        $('#price-selector').val('');
        $('#original-price').val(0);
        $('#stock-info').text('Stok: -');
    });

    // Tombol Hapus Item
    $('#parts-container').on('click', '.remove-item-btn', function() {
        $(this).closest('tr').remove();
    });

    // Validasi submit form (sudah benar)
    $('#edit-service-form').on('submit', function(e){
        let maxQty = parseInt($('#qty-selector').attr('max')) || 0;
        let currentQty = parseInt($('#qty-selector').val()) || 0;
        if(currentQty > maxQty){
            alert(`Jumlah yang dimasukkan (${currentQty}) melebihi stok yang tersedia (${maxQty}).`);
            e.preventDefault();
        }
    });
});
</script>
@endpush
