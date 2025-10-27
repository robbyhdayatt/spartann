@extends('adminlte::page')
@section('title', 'Buat Retur Pembelian')
@section('plugins.Select2', true) {{-- Aktifkan Select2 --}}

@section('content_header')
    <h1>Buat Retur Pembelian</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.purchase-returns.store') }}" method="POST" id="return-form">
        @csrf
        <div class="card-body">
            {{-- Tampilkan Error Validasi & Session --}}
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            {{-- Ubah format error array items.* menjadi lebih user-friendly --}}
                            <li>{{ preg_replace('/items\.(\d+)\.(.*)/', 'Item #$1: $2', $error) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            {{-- Input Header Retur --}}
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="receiving-select">Pilih Dokumen Penerimaan <span class="text-danger">*</span></label>
                    <select id="receiving-select" name="receiving_id" class="form-control select2 @error('receiving_id') is-invalid @enderror" required>
                        <option value="" disabled {{ old('receiving_id') ? '' : 'selected' }}>--- Pilih Penerimaan ---</option>
                        @foreach($receivings as $receiving)
                            {{-- Tambahkan info supplier & tanggal --}}
                            <option value="{{ $receiving->id }}" {{ old('receiving_id') == $receiving->id ? 'selected' : '' }}>
                                {{ $receiving->nomor_penerimaan }} -
                                {{ $receiving->purchaseOrder->supplier->nama_supplier ?? 'N/A' }} -
                                {{ \Carbon\Carbon::parse($receiving->tanggal_terima)->format('d/m/Y') }}
                            </option>
                        @endforeach
                    </select>
                     @error('receiving_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="col-md-6 form-group">
                    <label for="tanggal_retur">Tanggal Retur <span class="text-danger">*</span></label>
                    <input type="date" id="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" name="tanggal_retur" value="{{ old('tanggal_retur', now()->format('Y-m-d')) }}" required>
                     @error('tanggal_retur') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>
             <div class="form-group">
                <label for="catatan">Catatan</label>
                <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="2" placeholder="Catatan tambahan untuk retur (opsional)">{{ old('catatan') }}</textarea>
                 @error('catatan') <span class="invalid-feedback">{{ $message }}</span> @enderror
            </div>

             {{-- Tabel Item Retur --}}
            <h5 class="mt-4">Item untuk Diretur</h5>
            <p class="text-muted">Masukkan jumlah barang yang akan diretur pada kolom "Qty Diretur". Hanya item yang gagal QC dan belum diretur yang akan tampil.</p>
            <table class="table table-bordered table-sm"> {{-- Tambah table-sm --}}
                <thead class="thead-light">
                    <tr>
                        <th>Part</th>
                        <th style="width: 150px" class="text-center">Qty Gagal QC <br>(Tersedia Retur)</th>
                        <th style="width: 150px" class="text-center">Qty Diretur <span class="text-danger">*</span></th>
                        <th>Alasan Retur (Opsional)</th>
                    </tr>
                </thead>
                <tbody id="return-items-table">
                     {{-- Tampilkan placeholder jika belum ada penerimaan dipilih --}}
                     @if(!old('receiving_id'))
                     <tr><td colspan="4" class="text-center text-muted">Pilih dokumen penerimaan terlebih dahulu.</td></tr>
                     @endif
                     {{-- Items dimuat via JS --}}
                </tbody>
            </table>
            {{-- Tampilkan error khusus untuk array 'items' --}}
            @error('items')
                <div class="alert alert-danger mt-2">{{ $message }}</div>
             @enderror

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary" id="submit-button" disabled>Simpan Dokumen Retur</button> {{-- Awalnya disabled --}}
            <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@section('plugins.Select2', true) {{-- Pindahkan ke sini jika belum ada --}}

@section('js')
<script>
$(document).ready(function() {
    // Inisialisasi Select2
    $('.select2').select2({ theme: 'bootstrap4' });

    const receivingSelect = $('#receiving-select');
    const tableBody = $('#return-items-table');
    const submitButton = $('#submit-button');

    function loadItems() {
        let receivingId = receivingSelect.val();
        tableBody.html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
        submitButton.prop('disabled', true); // Disable tombol submit saat loading

        if (!receivingId) {
            tableBody.html('<tr><td colspan="4" class="text-center text-muted">Pilih dokumen penerimaan terlebih dahulu.</td></tr>');
            return;
        }

        // Pastikan route API ini ada dan mengembalikan JSON ReceivingDetail
        let urlTemplate = "{{ route('admin.api.receivings.failed-items', ['receiving' => ':id']) }}";
        let url = urlTemplate.replace(':id', receivingId);

        $.getJSON(url)
            .done(function(data) {
                tableBody.empty(); // Kosongkan tabel
                if (data && data.length > 0) {
                    let hasItemsToReturn = false; // Flag untuk cek apakah ada item valid
                    data.forEach(function(item) {
                        let availableToReturn = parseInt(item.qty_gagal_qc) - parseInt(item.qty_diretur || 0); // Pastikan qty_diretur ada
                        // Hanya tampilkan baris jika masih ada yang bisa diretur
                        if (availableToReturn > 0) {
                            hasItemsToReturn = true; // Set flag
                             // Ambil nilai lama jika ada (dari validation error)
                            let oldQty = "{{ old('items.:itemId.qty_retur') }}".replace(':itemId', item.id);
                            let oldAlasan = "{{ old('items.:itemId.alasan') }}".replace(':itemId', item.id);
                             // Nilai default qty_retur adalah jumlah tersedia, gunakan old value jika ada
                             let defaultValue = oldQty !== '' ? oldQty : availableToReturn;

                            let row = `
                                <tr class="item-row">
                                    <td>
                                        ${item.part.nama_part} (${item.part.kode_part})
                                        {{-- ++ TAMBAHKAN INPUT HIDDEN UNTUK receiving_detail_id ++ --}}
                                        <input type="hidden" name="items[${item.id}][receiving_detail_id]" value="${item.id}">
                                    </td>
                                    <td class="text-center">
                                         <input type="number" class="form-control-plaintext text-center available-qty" value="${availableToReturn}" readonly style="background: transparent; border: none;">
                                    </td>
                                    <td>
                                        <input type="number" name="items[${item.id}][qty_retur]"
                                               class="form-control qty-retur @error('items.'.$item->id.'.qty_retur') is-invalid @enderror"
                                               min="0" max="${availableToReturn}" value="${defaultValue}" required>
                                         {{-- Tampilkan error spesifik per baris jika ada --}}
                                         @error('items.${item.id}.qty_retur')
                                         <span class="invalid-feedback d-block">{{ $message }}</span>
                                         @enderror
                                    </td>
                                    <td>
                                        <input type="text" name="items[${item.id}][alasan]"
                                               class="form-control @error('items.'.$item->id.'.alasan') is-invalid @enderror"
                                               value="${oldAlasan || item.catatan_qc || ''}" placeholder="Alasan retur (Opsional)">
                                         @error('items.${item.id}.alasan')
                                         <span class="invalid-feedback d-block">{{ $message }}</span>
                                         @enderror
                                    </td>
                                </tr>`;
                            tableBody.append(row);
                        }
                    });

                    // Jika setelah loop, tidak ada item yg bisa diretur
                    if (!hasItemsToReturn) {
                         tableBody.html('<tr><td colspan="4" class="text-center text-info">Semua item gagal QC dari penerimaan ini sudah diretur.</td></tr>');
                         submitButton.prop('disabled', true); // Tetap disable tombol
                    } else {
                         submitButton.prop('disabled', false); // Enable tombol jika ada item
                    }

                } else {
                    tableBody.html('<tr><td colspan="4" class="text-center text-info">Tidak ada item Gagal QC yang bisa diretur dari penerimaan ini.</td></tr>');
                    submitButton.prop('disabled', true); // Disable tombol jika tidak ada item
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                 // Tampilkan pesan error yang lebih jelas
                 tableBody.html('<tr><td colspan="4" class="text-center text-danger">Gagal memuat item: ' + (jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Silakan cek log.') + '</td></tr>');
                 submitButton.prop('disabled', true); // Disable tombol jika error
            });
        } else {
            tableBody.empty(); // Kosongkan jika tidak ada receiving ID
            submitButton.prop('disabled', true); // Disable tombol
        }
    }

    // Listener untuk perubahan dropdown receiving
    receivingSelect.on('change', loadItems);

    // Initial load jika ada old input receiving_id saat halaman di-refresh setelah validation error
    if (receivingSelect.val()) {
        loadItems();
    }

     // Validasi jumlah retur di sisi client (opsional tapi baik)
     tableBody.on('input change', '.qty-retur', function() {
         let input = $(this);
         let max = parseInt(input.attr('max')) || 0;
         let val = parseInt(input.val()) || 0;
         if (val > max) {
             input.val(max); // Set ke nilai max jika melebihi
             alert('Jumlah retur tidak boleh melebihi jumlah tersedia!');
         }
         if (val < 0) { // Meskipun ada min="0", ini failsafe
             input.val(0);
         }
     });

});
</script>
@stop

@section('css')
{{-- Tambahkan sedikit CSS untuk input readonly agar terlihat seperti teks biasa --}}
<style>
    .form-control-plaintext {
        padding-top: .375rem;
        padding-bottom: .375rem;
        margin-bottom: 0;
        line-height: 1.5;
        background-color: transparent;
        border: solid transparent;
        border-width: 1px 0;
    }
     .invalid-feedback.d-block { display: block !important; } /* Pastikan error terlihat */
</style>
@stop