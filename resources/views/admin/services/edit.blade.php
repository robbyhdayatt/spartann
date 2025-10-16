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
                    <input type="hidden" id="service_lokasi_id" value="{{ $service->lokasi->id }}">
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
                    
                    <div class="row align-items-end">
                        <div class="col-md-5 form-group">
                            <label for="part-selector">Cari Part</label>
                            <select id="part-selector" class="form-control"></select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="qty-selector">Jumlah</label>
                            {{-- PERBAIKAN: Menambahkan elemen untuk info stok --}}
                            <div class="input-group">
                                <input type="number" id="qty-selector" class="form-control" min="1" value="1">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="stock-info">Stok: -</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="price-selector">Harga Satuan</label>
                            <input type="number" id="price-selector" class="form-control" min="0">
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
                    <a href="{{ route('admin.services.show', $service) }}" class="btn btn-secondary">Batal</a>
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

@push('js')
<script>
$(document).ready(function() {
    let itemIndex = 0;

    $('#part-selector').select2({
        theme: 'bootstrap4',
        placeholder: 'Ketik kode atau nama part...',
        ajax: {
            url: "{{ route('admin.parts.search') }}",
            dataType: 'json',
            delay: 250,
            // PERBAIKAN: Mengirim ID lokasi saat melakukan pencarian
            data: function (params) {
                return {
                    q: params.term, // Teks yang diketik user
                    lokasi_id: $('#service_lokasi_id').val() // Ambil ID lokasi dari hidden input
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
        $('#price-selector').val(data.harga_satuan || 0);
        
        // PERBAIKAN: Tampilkan stok dan set batas maksimal input qty
        $('#stock-info').text(`Stok: ${data.total_stock}`);
        $('#qty-selector').attr('max', data.total_stock);
    });

    // Tombol Tambah Part
    $('#add-part-btn').on('click', function() {
        let selectedPart = $('#part-selector').select2('data')[0];
        let qty = $('#qty-selector').val();
        let price = $('#price-selector').val();

        if (!selectedPart || !qty || qty <= 0 || !price || price < 0) {
            alert('Silakan pilih Part dan isi jumlah/harga yang valid.');
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
                <td>${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(price)}</td>
                <td>${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(subtotal)}</td>
                <td><button type="button" class="btn btn-xs btn-danger remove-item-btn"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        
        $('#parts-container').append(rowHtml);
        itemIndex++;

        // Reset input
        $('#part-selector').val(null).trigger('change');
        $('#qty-selector').val(1);
        $('#price-selector').val('');
    });

    // Tombol Hapus Item
    $('#parts-container').on('click', '.remove-item-btn', function() {
        $(this).closest('tr').remove();
    });

    $('#edit-service-form').on('submit', function(e){
        let maxQty = parseInt($('#qty-selector').attr('max')) || 0;
        let currentQty = parseInt($('#qty-selector').val()) || 0;
        if(currentQty > maxQty){
            alert(`Jumlah yang dimasukkan (${currentQty}) melebihi stok yang tersedia (${maxQty}).`);
            e.preventDefault(); // Mencegah form di-submit
        }
    });
});
</script>
@endpush