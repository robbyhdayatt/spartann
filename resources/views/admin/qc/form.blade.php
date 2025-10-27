@extends('adminlte::page')

@section('title', 'Proses Quality Control')

@section('content_header')
    <h1>Proses Quality Control: {{ $receiving->nomor_penerimaan }}</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.qc.store', $receiving->id) }}" method="POST">
        @csrf
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <p>Periksa setiap item dan masukkan jumlah yang lolos dan gagal pengecekan kualitas.</p>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th style="width: 120px">Qty Diterima</th>
                        <th style="width: 120px">Qty Lolos QC</th>
                        <th style="width: 120px">Qty Gagal QC</th>
                        <th style="width: 120px">Sisa</th>
                        <th>Catatan QC</th>
                    </tr>
                </thead>
                <tbody id="qc-items-table">
                    @foreach($receiving->details as $detail)
                    <tr class="qc-item-row">
                        <td>{{ $detail->part->nama_part }} ({{ $detail->part->kode_part }})</td>
                        <td>
                            <input type="number" class="form-control qty-diterima" value="{{ $detail->qty_terima }}" readonly>
                        </td>
                        <td>
                             <input type="number" name="items[{{ $detail->id }}][qty_lolos]" class="form-control qty-lolos" min="0" max="{{ $detail->qty_terima }}" value="{{ old('items.'.$detail->id.'.qty_lolos', $detail->qty_terima) }}" required>
                        </td>
                        <td>
                             <input type="number" name="items[{{ $detail->id }}][qty_gagal]" class="form-control qty-gagal" min="0" max="{{ $detail->qty_terima }}" value="{{ old('items.'.$detail->id.'.qty_gagal', 0) }}" required>
                        </td>
                        <td>
                            <input type="number" class="form-control sisa" value="0" readonly>
                        </td>
                        <td>
                            <input type="text" name="items[{{ $detail->id }}][catatan_qc]" class="form-control" value="{{ old('items.'.$detail->id.'.catatan_qc') }}" placeholder="Opsional">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" id="submit-btn" class="btn btn-primary">Simpan Hasil QC</button>
            <a href="{{ route('admin.qc.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    function validateRow(row) {
        let qtyDiterima = parseInt(row.find('.qty-diterima').val()) || 0;
        let qtyLolos = parseInt(row.find('.qty-lolos').val()) || 0;
        let qtyGagal = parseInt(row.find('.qty-gagal').val()) || 0;

        // Pastikan qty lolos/gagal tidak melebihi qty diterima secara individual
        // (Meskipun sudah ada 'max' di HTML, ini double check)
        if (qtyLolos > qtyDiterima) {
            qtyLolos = qtyDiterima;
            row.find('.qty-lolos').val(qtyLolos);
        }
        if (qtyGagal > qtyDiterima) {
            qtyGagal = qtyDiterima;
            row.find('.qty-gagal').val(qtyGagal);
        }

        let totalInput = qtyLolos + qtyGagal;
        let sisa = qtyDiterima - totalInput;
        let sisaInput = row.find('.sisa');
        sisaInput.val(sisa);

        // ++ PERBAIKAN: Beri class 'is-invalid' jika sisa TIDAK sama dengan 0 ++
        if (sisa !== 0) {
            sisaInput.addClass('is-invalid');
            // Opsional: beri style juga ke input lolos/gagal jika totalnya salah
            // row.find('.qty-lolos, .qty-gagal').addClass('is-invalid');
        } else {
            sisaInput.removeClass('is-invalid');
            // row.find('.qty-lolos, .qty-gagal').removeClass('is-invalid');
        }
        validateAllRows(); // Panggil validasi global setiap ada perubahan
    }

    function validateAllRows() {
        let isFormValid = true;
        $('.qc-item-row').each(function() {
            // Cek input lolos & gagal apakah valid (angka dan min 0)
            let lolosInput = $(this).find('.qty-lolos');
            let gagalInput = $(this).find('.qty-gagal');
            let qtyLolos = parseInt(lolosInput.val()) || 0;
            let qtyGagal = parseInt(gagalInput.val()) || 0;

            // ++ PERBAIKAN: Tombol submit di-disable jika ada sisa TIDAK 0
            //    atau jika input lolos/gagal < 0 (meskipun dicegah HTML min="0")
            let sisa = parseInt($(this).find('.sisa').val());
            if (sisa !== 0 || qtyLolos < 0 || qtyGagal < 0) {
                isFormValid = false;
                return false; // Hentikan loop .each jika ditemukan 1 baris invalid
            }
        });
        $('#submit-btn').prop('disabled', !isFormValid);
    }

    // Listener saat input qty lolos atau gagal berubah
    $('#qc-items-table').on('input change', '.qty-lolos, .qty-gagal', function() {
        validateRow($(this).closest('tr'));
    });

    // Validasi semua baris saat halaman dimuat
    $('.qc-item-row').each(function() {
        validateRow($(this));
    });
});
</script>
@stop

@section('css')
<style>.is-invalid { background-color: #f8d7da !important; border-color: #f5c6cb !important; }</style>
@stop