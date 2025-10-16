@extends('adminlte::page')

@section('title', 'Buat Penjualan Baru')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Penjualan Baru</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.penjualans.store') }}" method="POST">
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
            @if(session('error'))
                 <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="gudang_id">Lokasi Penjualan</label>
                    <input type="text" class="form-control" value="{{ $lokasi->nama_gudang }}" readonly>
                    <input type="hidden" id="gudang_id" name="gudang_id" value="{{ $lokasi->id }}">
                </div>
                <div class="col-md-4 form-group">
                    <label for="konsumen_id">Konsumen <span class="text-danger">*</span></label>
                    <select class="form-control select2bs4" id="konsumen_id" name="konsumen_id" required>
                        <option value="">Pilih Konsumen</option>
                        @foreach($konsumens as $konsumen)
                            <option value="{{ $konsumen->id }}" {{ old('konsumen_id') == $konsumen->id ? 'selected' : '' }}>{{ $konsumen->nama_konsumen }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="tanggal_jual">Tanggal Jual <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggal_jual" name="tanggal_jual" value="{{ old('tanggal_jual', date('Y-m-d')) }}" required>
                </div>
            </div>
            <hr>
            <div class="row align-items-end">
                <div class="col-md-5 form-group">
                    <label for="part-selector">Pilih Part</label>
                    <select id="part-selector" class="form-control select2bs4" disabled>
                        <option>Pilih Lokasi & Konsumen dahulu</option>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="qty-selector">Jumlah</label>
                    <input type="number" id="qty-selector" class="form-control" min="1">
                </div>
                <div class="col-md-2 form-group">
                    <button type="button" class="btn btn-primary" id="add-part-btn">Tambahkan</button>
                </div>
            </div>
            <hr>

            <h5>Detail Part yang Akan Dijual</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Part</th>
                            <th style="width: 20%;">Rak (Otomatis FIFO)</th>
                            <th style="width: 10%;">Qty</th>
                            <th>Harga Jual</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="parts-container"></tbody>
                </table>
            </div>

            <div class="row mt-4">
                <div class="col-md-6 offset-md-6">
                    <div class="table-responsive">
                        <table class="table">
                            {{-- ++ PERUBAHAN: Tampilan total disederhanakan ++ --}}
                            <tr><th style="width:50%">Subtotal:</th><td class="text-right" id="subtotal-text">Rp 0</td></tr>
                            <tr><th>Total Diskon:</th><td class="text-right text-success" id="diskon-text">Rp 0</td></tr>
                            <tr><th>Total Keseluruhan:</th><td class="text-right h4" id="total-text">Rp 0</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            <button type="submit" class="btn btn-primary">Simpan Penjualan</button>
            <a href="{{ route('admin.penjualans.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    let partsData = {};
    let itemIndex = 0;
    $('.select2bs4').select2({ theme: 'bootstrap4' });

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    function calculateTotal() {
        let subtotal = 0;
        let totalDiskon = 0;
        $('.part-row').each(function() {
            let hargaFinal = parseFloat($(this).find('.harga-final-input').val()) || 0;
            let qty = parseInt($(this).find('.qty-input').val()) || 0;
            subtotal += hargaFinal * qty;
        });
        
        // Pajak sudah tidak ada, total = subtotal
        let total = subtotal;
        
        $('#subtotal-text').text(formatRupiah(subtotal));
        $('#diskon-text').text(formatRupiah(0)); // Diskon di-nol-kan di tampilan
        $('#total-text').text(formatRupiah(total));
    }

    function loadParts() {
        let gudangId = $('#gudang_id').val();
        let konsumenId = $('#konsumen_id').val();
        let partSelector = $('#part-selector');
        
        if (!gudangId || !konsumenId) {
            partSelector.prop('disabled', true).html('<option>Pilih Lokasi & Konsumen dahulu</option>');
            return;
        }

        partSelector.prop('disabled', true).html('<option>Memuat Part...</option>');
        let url = `{{ url('admin/api/lokasi') }}/${gudangId}/parts`;

        $.ajax({
            url: url,
            success: function(parts) {
                partsData = {}; // Reset data
                partSelector.prop('disabled', false).html('<option value="">Pilih Part</option>');
                parts.forEach(part => {
                    // Simpan data part lengkap, termasuk harga
                    partsData[part.id] = part; 
                    partSelector.append(`<option value="${part.id}" data-total-stock="${part.total_stock}">${part.kode_part} - ${part.nama_part} (Stok: ${part.total_stock})</option>`);
                });
            },
            error: function() { partSelector.html('<option>Gagal memuat part</option>'); }
        });
    }

    // Panggil loadParts saat konsumen atau lokasi berubah
    $('#konsumen_id').on('change', loadParts);
    loadParts();

    $('#add-part-btn').on('click', function() {
        let partId = $('#part-selector').val();
        let qtyJual = parseInt($('#qty-selector').val());
        let gudangId = $('#gudang_id').val();

        if (!partId || !qtyJual || qtyJual <= 0) {
            alert('Silakan pilih Part dan isi jumlah yang valid.');
            return;
        }

        if ($(`.part-row[data-part-id="${partId}"]`).length > 0) {
            alert('Part ini sudah ditambahkan. Hapus terlebih dahulu jika ingin mengubah jumlah.');
            return;
        }

        let selectedPartData = partsData[partId];
        let hargaFinal = selectedPartData.harga_satuan;

        $.ajax({
            url: `{{ route('admin.api.get-fifo-batches') }}`,
            data: { part_id: partId, gudang_id: gudangId },
            success: function(batches) {
                let sisaQty = qtyJual;
                for (const batch of batches) {
                    if (sisaQty <= 0) break;
                    let qtyAmbil = Math.min(sisaQty, batch.quantity);

                    let newRowHtml = `
                        <tr class="part-row" data-part-id="${partId}">
                            <td>
                                ${selectedPartData.nama_part}
                                <input type="hidden" name="items[${itemIndex}][part_id]" value="${partId}">
                                <input type="hidden" name="items[${itemIndex}][batch_id]" value="${batch.id}">
                                <input type="hidden" class="harga-final-input" value="${hargaFinal}">
                            </td>
                            <td>${batch.rak.kode_rak}</td>
                            <td><input type="number" class="form-control qty-input" name="items[${itemIndex}][qty_jual]" value="${qtyAmbil}" readonly></td>
                            <td class="text-right">${formatRupiah(hargaFinal)}</td>
                            <td class="text-right subtotal-row-text">${formatRupiah(hargaFinal * qtyAmbil)}</td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-part-btn"><i class="fas fa-trash"></i></button></td>
                        </tr>
                    `;
                    $('#parts-container').append(newRowHtml);
                    sisaQty -= qtyAmbil;
                    itemIndex++;
                }

                if (sisaQty > 0) {
                    alert(`Stok tidak mencukupi. Hanya tersedia ${qtyJual - sisaQty} unit.`);
                }

                calculateTotal();
                $('#part-selector').val('').trigger('change');
                $('#qty-selector').val('');
            },
            error: function() {
                alert('Gagal mengambil data batch stok.');
            }
        });
    });

    $('#parts-container').on('click', '.remove-part-btn', function() {
        let partId = $(this).closest('tr').data('part-id');
        $(`tr[data-part-id="${partId}"]`).remove();
        calculateTotal();
    });
});
</script>
@stop