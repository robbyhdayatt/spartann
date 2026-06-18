@php
use App\Helpers\NumberHelper;
// Kalkulasi Total Service dan Sparepart
$totalService = $service->details->where('item_category', 'JASA')->sum(function ($item) {
    return $item->quantity * $item->price;
});
$totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(function ($item) {
    return $item->quantity * $item->price;
});
@endphp

@extends('adminlte::page')

@section('title', 'Invoice ' . $service->invoice_no)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Faktur Service: {{ $service->invoice_no }}</h1>
        <div>
            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            
            {{-- TOMBOL SAKTI: Mode Edit + Unduh Otomatis --}}
            <button onclick="generateAndDownloadPDF()" class="btn btn-danger shadow-sm" id="btn-download-pdf">
                <i class="fas fa-file-pdf"></i> Simpan & Unduh PDF
            </button>
        </div>
    </div>
@stop

@section('content')
<div class="alert alert-info alert-dismissible no-print shadow-sm">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    <h5><i class="icon fas fa-info-circle"></i> Mode Edit Cepat & Auto PDF!</h5>
    Area dengan <i>background</i> kuning putus-putus <b>bisa Anda ketik/edit secara langsung</b>. 
    <br>Klik tombol <b>"Simpan & Unduh PDF"</b>, sistem akan mengambil editan Anda dan mengunduh file PDF-nya secara otomatis ke perangkat Anda!
</div>

{{-- Pembungkus utama untuk mensimulasikan kertas di layar browser --}}
<div class="invoice-container">
    <div class="invoice-wrapper" id="print-area">
        @include('admin.services.pdf_content', ['service' => $service, 'totalService' => $totalService, 'totalSparepart' => $totalSparepart])
    </div>
</div>
@stop

@push('js')
{{-- Panggil Library html2pdf.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    function generateAndDownloadPDF() {
        let btn = document.getElementById('btn-download-pdf');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Memproses...';
        btn.disabled = true;

        let printArea = document.getElementById('print-area');
        printArea.classList.add('pdf-rendering-mode');

        // Pengaturan PDF dengan tambahan fitur PAGEBREAK
        let opt = {
            margin:       [0.2, 0.2], 
            filename:     'Invoice-{{ $service->invoice_no }}.pdf',
            image:        { type: 'jpeg', quality: 1 },
            html2canvas:  { scale: 2, useCORS: true, scrollY: 0 },
            jsPDF:        { unit: 'cm', format: [21.5, 14], orientation: 'landscape' },
            // [BARIS BARU] Mencegah teks terpotong di tengah jalan. Memaksa baris tabel utuh pindah ke halaman berikutnya.
            pagebreak:    { avoid: ['tr', '.signature-box'] } 
        };

        // Langsung eksekusi save() tanpa membuka tab baru
        html2pdf().set(opt).from(printArea).save().then(() => {
            
            // Kembalikan tampilan layar
            printArea.classList.remove('pdf-rendering-mode');
            
            // Update status ke backend (database) diam-diam
            fetch("{{ route('admin.services.mark_printed', $service->id) }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            }).then(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Selesai!';
                setTimeout(function(){ window.location.reload(); }, 1500);
            });

        }).catch(err => {
            console.error("Gagal membuat PDF: ", err);
            printArea.classList.remove('pdf-rendering-mode');
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert("Terjadi kesalahan saat memproses PDF.");
        });
    }
</script>
@endpush

@push('css')
<style>
    /* Agar di browser tidak memanjang secara aneh */
    .invoice-container {
        width: 100%;
        overflow-x: auto;
        background: transparent;
        padding-bottom: 20px;
    }

    /* Simulasi lebar kertas Continuous Form di layar */
    .invoice-wrapper { 
        border: 1px solid #ddd; 
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); 
        background-color: #fff; 
        padding: 15px; 
        width: 800px; 
        margin: 0 auto;
    }
    
    /* === KOTAK EDIT DI LAYAR BROWSER === */
    .editable-area {
        border: 1px dashed #ffc107;
        background-color: #fffdf5;
        cursor: text;
        padding: 1px 4px;
        border-radius: 2px;
        transition: 0.2s;
    }
    .editable-area:focus {
        outline: none;
        background-color: #fff3cd;
        border-color: #ff9800;
    }

    /* === SULAP SAAT PDF DI-GENERATE === */
    .pdf-rendering-mode {
        border: none !important;
        box-shadow: none !important;
        padding: 10px !important;
    }
    .pdf-rendering-mode .editable-area {
        border: none !important;
        background-color: transparent !important;
        padding: 0 !important;
    }
    .pdf-rendering-mode .editable-area[placeholder]:empty:before {
        content: "" !important;
    }
</style>
@endpush