@extends('adminlte::page')

@section('title', 'Proses Quality Control')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-clipboard-check text-primary mr-2"></i> Proses Quality Control</h1>
        <div class="text-right">
            <span class="badge badge-secondary" style="font-size: 1rem;">No: {{ $receiving->nomor_penerimaan }}</span>
            <br>
            <small class="text-muted">Tanggal Terima: {{ $receiving->tanggal_terima->format('d M Y') }}</small>
        </div>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        
        {{-- Progress / Steps Wizard (Visual Enhancement) --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 33%" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100">1. Receiving</div>
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 34%" aria-valuenow="34" aria-valuemin="0" aria-valuemax="100">2. Quality Control (Sedang Proses)</div>
                    <div class="progress-bar bg-secondary" role="progressbar" style="width: 33%" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100">3. Putaway</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.qc.store', $receiving->id) }}" method="POST" id="qc-form">
                @csrf
                
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="alert alert-light border-left-primary" role="alert">
                        <i class="fas fa-info-circle text-primary mr-1"></i> 
                        Masukkan jumlah barang yang <strong>Lolos</strong> dan <strong>Gagal</strong>. Pastikan kolom <strong>Sisa</strong> bernilai <strong>0</strong>.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover v-middle">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 25%">Barang / Suku Cadang</th>
                                    <th class="text-center" style="width: 12%">Diterima</th>
                                    <th class="text-center" style="width: 18%">Lolos (Bagus)</th>
                                    <th class="text-center" style="width: 18%">Gagal (Rusak)</th>
                                    <th class="text-center" style="width: 10%">Sisa</th>
                                    <th style="width: 17%">Catatan</th>
                                </tr>
                            </thead>
                            <tbody id="qc-items-table">
                                @foreach($receiving->details as $detail)
                                <tr class="qc-item-row" data-qty-terima="{{ $detail->qty_terima }}">
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40 mr-3">
                                                <div class="symbol-label bg-light-primary rounded p-2">
                                                    <i class="fas fa-cogs text-primary fa-lg"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-dark font-weight-bold d-block">{{ $detail->barang->part_name }}</span>
                                                <span class="text-muted small font-weight-bold">{{ $detail->barang->part_code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="align-middle text-center">
                                        <span class="badge badge-light border px-3 py-2" style="font-size: 1rem;">
                                            {{ $detail->qty_terima }}
                                        </span>
                                        <input type="hidden" class="qty-diterima" value="{{ $detail->qty_terima }}">
                                    </td>

                                    <td class="align-middle">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-success border-success text-white">
                                                    <i class="fas fa-check"></i>
                                                </span>
                                            </div>
                                            <input type="number" name="items[{{ $detail->id }}][qty_lolos]" 
                                                   class="form-control text-center font-weight-bold qty-lolos" 
                                                   min="0" max="{{ $detail->qty_terima }}" 
                                                   value="{{ old('items.'.$detail->id.'.qty_lolos', $detail->qty_terima) }}" 
                                                   required placeholder="0">
                                        </div>
                                    </td>

                                    <td class="align-middle">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-danger border-danger text-white">
                                                    <i class="fas fa-times"></i>
                                                </span>
                                            </div>
                                            <input type="number" name="items[{{ $detail->id }}][qty_gagal]" 
                                                   class="form-control text-center font-weight-bold qty-gagal" 
                                                   min="0" max="{{ $detail->qty_terima }}" 
                                                   value="{{ old('items.'.$detail->id.'.qty_gagal', 0) }}" 
                                                   required placeholder="0">
                                        </div>
                                    </td>

                                    <td class="align-middle text-center p-0">
                                        <input type="text" class="form-control-plaintext text-center font-weight-bold sisa-text" value="0" readonly>
                                        <input type="hidden" class="sisa" value="0">
                                    </td>

                                    <td class="align-middle">
                                        <textarea name="items[{{ $detail->id }}][catatan_qc]" class="form-control form-control-sm" rows="1" placeholder="Ket. Kerusakan...">{{ old('items.'.$detail->id.'.catatan_qc') }}</textarea>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.qc.index') }}" class="btn btn-default shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Batal & Kembali
                    </a>
                    <button type="submit" id="submit-btn" class="btn btn-primary shadow-sm px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Hasil QC
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('css')
<style>
    /* Styling khusus untuk tabel */
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    /* Input qty lebih bold */
    .qty-lolos, .qty-gagal {
        font-size: 1.1rem;
        color: #495057;
    }
    .qty-lolos:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    .qty-gagal:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    /* Style untuk kolom Sisa */
    .sisa-valid {
        background-color: #d4edda;
        color: #155724;
    }
    .sisa-invalid {
        background-color: #f8d7da;
        color: #721c24;
        animation: pulse 2s infinite;
    }

    .border-left-primary {
        border-left: 4px solid #007bff;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    /* Hilangkan spinner di input number */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type=number] {
      -moz-appearance: textfield;
    }
</style>
@stop

@section('js')
<script>
$(document).ready(function() {
    
    // --- 1. UX: AUTO CLEAR/SELECT ON FOCUS ---
    $('#qc-items-table').on('focus', '.qty-lolos, .qty-gagal', function() {
        var val = $(this).val();
        if (val == 0) {
            $(this).val('');
        } else {
            $(this).select();
        }
    });

    $('#qc-items-table').on('blur', '.qty-lolos, .qty-gagal', function() {
        if ($(this).val() === '') {
            $(this).val(0);
        }
        validateRow($(this).closest('tr'));
    });
    // -----------------------------------------

    // --- 2. VALIDATION LOGIC ---
    function validateRow(row) {
        let qtyDiterima = parseInt(row.find('.qty-diterima').val()) || 0;
        let qtyLolosInput = row.find('.qty-lolos');
        let qtyGagalInput = row.find('.qty-gagal');
        
        let qtyLolos = parseInt(qtyLolosInput.val()) || 0;
        let qtyGagal = parseInt(qtyGagalInput.val()) || 0;

        // Auto-adjust jika melebihi (Opsional, tapi membantu)
        /*
        if (qtyLolos > qtyDiterima) { qtyLolos = qtyDiterima; qtyLolosInput.val(qtyLolos); }
        if (qtyGagal > qtyDiterima) { qtyGagal = qtyDiterima; qtyGagalInput.val(qtyGagal); }
        */

        let totalInput = qtyLolos + qtyGagal;
        let sisa = qtyDiterima - totalInput;
        
        let sisaField = row.find('.sisa-text');
        let sisaHidden = row.find('.sisa');
        
        sisaHidden.val(sisa);
        sisaField.val(sisa);

        // Visual Feedback untuk Kolom Sisa
        sisaField.removeClass('sisa-valid sisa-invalid');
        if (sisa === 0) {
            sisaField.addClass('sisa-valid');
            row.removeClass('table-danger');
        } else {
            sisaField.addClass('sisa-invalid');
            row.addClass('table-danger'); // Highlight baris yang salah
        }
        
        validateGlobal();
    }

    function validateGlobal() {
        let isFormValid = true;
        $('.qc-item-row').each(function() {
            let sisa = parseInt($(this).find('.sisa').val());
            let qtyLolos = parseInt($(this).find('.qty-lolos').val()) || 0;
            let qtyGagal = parseInt($(this).find('.qty-gagal').val()) || 0;
            let qtyDiterima = parseInt($(this).find('.qty-diterima').val());

            // Syarat valid: Sisa harus 0, dan total input harus = diterima
            if (sisa !== 0 || qtyLolos < 0 || qtyGagal < 0 || (qtyLolos + qtyGagal) != qtyDiterima) {
                isFormValid = false;
                return false; 
            }
        });
        
        let btn = $('#submit-btn');
        if(isFormValid) {
            btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Hasil QC').removeClass('btn-secondary').addClass('btn-primary');
        } else {
            btn.prop('disabled', true).html('<i class="fas fa-exclamation-circle mr-1"></i> Data Belum Sesuai').removeClass('btn-primary').addClass('btn-secondary');
        }
    }

    // Listener Input
    $('#qc-items-table').on('input change keyup', '.qty-lolos, .qty-gagal', function() {
        validateRow($(this).closest('tr'));
    });

    // Init Validation saat Load
    $('.qc-item-row').each(function() {
        validateRow($(this));
    });
});
</script>
@stop