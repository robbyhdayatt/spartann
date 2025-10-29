@extends('adminlte::page')
@section('title', 'Set Target Penjualan')
@section('content_header')<h1>Set Target Penjualan Sales</h1>@stop
@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Filter Periode</h3></div>
    <div class="card-body">
        <form action="{{ route('admin.incentives.targets') }}" method="GET">
            <div class="row">
                <div class="col-md-5 form-group">
                    <label>Tahun</label>
                    <select name="tahun" class="form-control">
                        @for ($y = now()->year; $y >= 2023; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-5 form-group">
                    <label>Bulan</label>
                    <select name="bulan" class="form-control">
                        @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label> <button type="submit" class="btn btn-primary btn-block">Tampilkan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <form action="{{ route('admin.incentives.targets.store') }}" method="POST">
        @csrf
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="card-header"><h3 class="card-title">Input Target untuk {{ \Carbon\Carbon::create()->month($bulan)->format('F') }} {{ $tahun }}</h3></div>
        <div class="card-body">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <table class="table table-bordered">
                <thead><tr><th>Nama Sales</th><th style="width: 300px;">Target Penjualan (Rp)</th></tr></thead>
                <tbody>
                    @foreach($salesUsers as $sales)
                    @php
                        // Ambil nilai mentah (angka) dari old() atau data yang ada
                        $rawValue = old('targets.' . $sales->id, $existingTargets[$sales->id] ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $sales->nama }}</td>
                        <td>
                            {{-- Input tersembunyi (hidden) untuk menyimpan nilai mentah/angka --}}
                            {{-- Ini yang akan dikirim ke controller --}}
                            <input type="hidden"
                                   name="targets[{{ $sales->id }}]"
                                   class="target-hidden-input"
                                   id="target-hidden-{{ $sales->id }}"
                                   value="{{ $rawValue }}">

                            {{-- Input terlihat (visible) untuk tampilan format XXX.XXX.XXX --}}
                            {{-- Input ini TIDAK punya atribut 'name' --}}
                            <input type="text"
                                   class="form-control target-input-formatted"
                                   data-target="#target-hidden-{{ $sales->id }}" {{-- Link ke input hidden --}}
                                   value="{{ number_format($rawValue, 0, ',', '.') }}" {{-- Tampilkan nilai terformat --}}
                                   required>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary">Simpan Target</button></div>
    </form>
</div>
@stop

{{-- Tambahkan section JS --}}
@push('js')
<script>
$(document).ready(function() {

    // Fungsi untuk memformat angka (1000000 -> "1.000.000")
    function formatNumber(numStr) {
        // Hapus semua non-digit
        let num = String(numStr).replace(/[^0-9]/g, '');
        // Cegah '0' di depan jika lebih dari 1 digit (misal '0123')
        if (num.length > 1 && num.startsWith('0')) {
             num = num.substring(1);
        }
        if (num === '') {
            return '0'; // Jika kosong, kembalikan 0
        }
        // Format dengan titik
        return num.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Fungsi untuk mendapatkan angka mentah ("1.000.000" -> 1000000)
    function parseNumber(str) {
        return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0;
    }

    // Inisialisasi semua input yang ada saat halaman dimuat
    // (Penting untuk menangani nilai 'old' saat validasi error)
    $('.target-input-formatted').each(function() {
        let visibleInput = $(this);
        let targetHiddenId = visibleInput.data('target');
        let hiddenInput = $(targetHiddenId);

        let rawValue = hiddenInput.val();
        // Format nilai yang ada di hidden input ke visible input
        visibleInput.val(formatNumber(rawValue));
    });

    // Event listener saat Kursor Masuk (Fokus)
    $(document).on('focus', '.target-input-formatted', function() {
        let visibleInput = $(this);

        // Ambil nilai yang sedang terlihat
        let currentValue = visibleInput.val();

        // Jika nilainya "0", kosongkan input terlihat
        if (currentValue === '0') {
            visibleInput.val('');
        }
    });

    // Event listener saat Kursor Keluar (Blur)
    $(document).on('blur', '.target-input-formatted', function() {
        let visibleInput = $(this);
        let targetHiddenId = visibleInput.data('target');
        let hiddenInput = $(targetHiddenId);

        // Ambil nilai mentah dari input tersembunyi (yang sudah diupdate oleh 'input' event)
        let rawValue = parseNumber(hiddenInput.val());

        // Jika input terlihat kosong atau nilainya 0, set ke "0"
        if (visibleInput.val() === '' || rawValue === 0) {
            visibleInput.val('0');
            hiddenInput.val(0); // Pastikan hidden juga 0
        } else {
            // Format ulang untuk memastikan (misal user ketik 1000000 lalu blur)
             visibleInput.val(formatNumber(rawValue));
        }
    });

    // Event listener saat Mengetik (Input)
    $(document).on('input', '.target-input-formatted', function() {
        let visibleInput = $(this);
        let targetHiddenId = visibleInput.data('target');
        let hiddenInput = $(targetHiddenId);

        // Ambil nilai mentah dari apa yang diketik
        let rawValue = parseNumber(visibleInput.val());

        // Simpan nilai mentah di input tersembunyi
        hiddenInput.val(rawValue);

        // Format ulang nilai yang terlihat (ini penting agar titik otomatis muncul)
        // Kita simpan posisi kursor agar tidak loncat
        let cursorPos = this.selectionStart;
        let oldLength = visibleInput.val().length;

        let formattedValue = formatNumber(rawValue);
        visibleInput.val(formattedValue);

        let newLength = visibleInput.val().length;

        // Sesuaikan posisi kursor
        // Hitung selisih jumlah titik
        let oldDots = (visibleInput.val().substring(0, cursorPos).match(/\./g) || []).length;
        let newDots = (formattedValue.substring(0, cursorPos + (newLength - oldLength)).match(/\./g) || []).length;
        let dotDiff = newDots - oldDots;

        let newCursorPos = cursorPos + (newLength - oldLength);

        // Handle jika kursor ada di awal angka '0'
        if (rawValue === 0 && visibleInput.val() !== '0') {
             newCursorPos = 0;
        }

        this.setSelectionRange(newCursorPos, newCursorPos);
    });

});
</script>
@endpush
