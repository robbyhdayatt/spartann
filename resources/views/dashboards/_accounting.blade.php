@php
    $fmtStart = \Carbon\Carbon::parse($data['filter']['startDate'])->translatedFormat('d M Y');
    $fmtEnd = \Carbon\Carbon::parse($data['filter']['endDate'])->translatedFormat('d M Y');
    $fmtPrevStart = \Carbon\Carbon::parse($data['filter']['prevStartDate'])->translatedFormat('d M Y');
    $fmtPrevEnd = \Carbon\Carbon::parse($data['filter']['prevEndDate'])->translatedFormat('d M Y');
    
    $lblCurrent = "Periode Filter ($fmtStart - $fmtEnd)";
    $lblPrevious = "Bulan Lalu ($fmtPrevStart - $fmtPrevEnd)";
@endphp

<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-calculator mr-2"></i> Financial & Audit Dashboard (Accounting)</h4>
        <p class="text-muted">Laporan Keuangan Hybrid Terkonsolidasi & Volume Dokumen</p>
    </div>
</div>

{{-- FORM FILTER GANDA (SELECT2 & AJAX YGP) --}}
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body py-3">
                <form action="{{ route('admin.home') }}" method="GET">
                    <div class="row align-items-end">
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-muted">Tanggal Awal</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $data['filter']['startDate'] }}">
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-muted">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $data['filter']['endDate'] }}">
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <label class="text-xs text-muted">Filter Dealer</label>
                            <select name="lokasi_id" class="form-control form-control-sm select2">
                                <option value="all">Semua Dealer Aktif</option>
                                @foreach($data['daftarLokasi'] as $loc)
                                    <option value="{{ $loc->id }}" {{ $data['filter']['filterLokasi'] == $loc->id ? 'selected' : '' }}>{{ $loc->nama_lokasi }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        {{-- [MODIFIKASI] FILTER NON-YGP DENGAN OPSI SEMBUNYIKAN --}}
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-primary font-weight-bold">Filter Non-YGP</label>
                            <select name="barang_id" class="form-control form-control-sm select2">
                                <option value="all" {{ $data['filter']['filterNonYgp'] == 'all' ? 'selected' : '' }}>Semua Non-YGP</option>
                                <option value="none" {{ $data['filter']['filterNonYgp'] == 'none' ? 'selected' : '' }}>Sembunyikan Non-YGP</option>
                                @foreach($data['daftarBarang'] as $brg)
                                    <option value="{{ $brg->id }}" {{ $data['filter']['filterNonYgp'] == $brg->id ? 'selected' : '' }}>{{ $brg->part_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        {{-- [MODIFIKASI] FILTER YGP DENGAN OPSI SEMBUNYIKAN --}}
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-danger font-weight-bold">Filter YGP (AJAX)</label>
                            <select name="part_code" id="select2-ygp" class="form-control form-control-sm">
                                @if($data['filter']['filterYgp'] === 'all')
                                    <option value="all" selected>Semua YGP</option>
                                    <option value="none">Sembunyikan YGP</option>
                                @elseif($data['filter']['filterYgp'] === 'none')
                                    <option value="all">Semua YGP</option>
                                    <option value="none" selected>Sembunyikan YGP</option>
                                @else
                                    <option value="all">Semua YGP</option>
                                    <option value="none">Sembunyikan YGP</option>
                                    <option value="{{ $data['filter']['filterYgp'] }}" selected>{{ $data['selectedYgpName'] }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter"></i> Go</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<hr>

{{-- METRIK KESEHATAN LABA (OMSET & ASET) --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info shadow-sm">
            <div class="inner">
                <h3>{{ number_format($data['totalFaktur'], 0, ',', '.') }}</h3>
                <p>Total Transaksi (Faktur)</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice"></i></div>
            <div class="small-box-footer" title="Gabungan Service & Penjualan Retail">
                Service & Penjualan <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success shadow-sm">
            <div class="inner">
                <h3>Rp {{ number_format($data['grandTotalOmset'], 0, ',', '.') }}</h3>
                <p>Total Pendapatan (Omset Netto)</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            <div class="small-box-footer">Sesuai Filter Form <i class="fas fa-filter"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary shadow-sm">
            <div class="inner">
                <h3>Rp {{ number_format($data['grandTotalLaba'], 0, ',', '.') }}</h3>
                <p>Laba Kotor (Setelah Diskon)</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="small-box-footer">Sesuai Filter Form <i class="fas fa-filter"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box {{ $data['grossProfitMargin'] > 15 ? 'bg-secondary' : 'bg-warning' }} shadow-sm">
            <div class="inner text-white">
                <h3>{{ $data['grossProfitMargin'] }} <sup style="font-size: 20px">%</sup></h3>
                <p>Gross Profit Margin (Netto)</p>
            </div>
            <div class="icon"><i class="fas fa-percent"></i></div>
            <div class="small-box-footer text-white">Kesehatan Laba <i class="fas fa-heartbeat"></i></div>
        </div>
    </div>
</div>

{{-- OMSET COMPARISON CHARTS (CURRENT VS PREVIOUS) --}}
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1">
                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-left text-success mr-2"></i> Perbandingan Retail (Omset)</h3>
                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
            </div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareRetailOmset"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1">
                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-right text-info mr-2"></i> Perbandingan Service (Omset)</h3>
                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
            </div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareServiceOmset"></canvas></div></div>
        </div>
    </div>
</div>

{{-- OMSET DISTRIBUTION & TREND CHARTS --}}
<div class="row mt-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-secondary mr-2"></i> Komposisi Omset Netto</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="pieOmset"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-line text-success mr-2"></i> Tren Nilai Keluar Harian</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barOmset"></canvas></div></div>
        </div>
    </div>
</div>

{{-- TOP PERFORMER & RECENT TRANSACTIONS --}}
<div class="row mt-3">
    {{-- Top 5 Barang --}}
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-medal text-warning mr-2"></i> Top 5 Part (Rupiah)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-3">Nama Barang</th><th class="text-right pr-3">Omset</th></tr></thead>
                    <tbody>
                        @forelse($data['topItemsOmset'] as $item)
                        <tr><td class="pl-3 align-middle font-weight-bold text-truncate" style="max-width: 150px;">{{ $item->name }}</td><td class="text-right pr-3 text-success font-weight-bold">Rp {{ number_format($item->omset, 0, ',', '.') }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top 5 Cabang --}}
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-store text-danger mr-2"></i> Top 5 Dealer (Omset)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-3">Dealer</th><th class="text-right pr-3">Rupiah</th></tr></thead>
                    <tbody>
                        @forelse($data['topCabangOmset'] as $cab)
                        <tr><td class="pl-3 align-middle font-weight-bold">{{ $cab->nama_lokasi }}</td><td class="text-right pr-3 text-primary font-weight-bold">Rp {{ number_format($cab->omset, 0, ',', '.') }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Audit Faktur (Khusus Accounting) --}}
    <div class="col-md-4">
        <div class="card card-outline card-warning shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-history text-dark mr-2"></i> Audit Faktur Terakhir</h3></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-3">No. Faktur</th><th class="text-right pr-3">Total (Rp)</th></tr></thead>
                    <tbody>
                        @forelse($data['recentTransactions'] as $trx)
                            <tr>
                                <td class="pl-3 align-middle">
                                    <span class="font-weight-bold">{{ $trx->nomor_faktur }}</span><br>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{ $trx->lokasi->kode_lokasi ?? '-' }}</small>
                                </td>
                                <td class="text-right pr-3 align-middle font-weight-bold text-dark">Rp {{ number_format($trx->total_harga, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- [MODIFIKASI 3]: MONITORING STOK DEALER --}}
<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pb-2">
                <h3 class="card-title font-weight-bold"><i class="fas fa-boxes text-success mr-2"></i> Monitoring Stok Jaringan Dealer</h3>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="pl-4">Dealer</th>
                            <th>Kode Part</th>
                            <th>Nama Barang</th>
                            <th class="text-center">Sisa Stok Fisik</th>
                            <th class="text-center">Batas Minimum</th>
                            <th class="text-center pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['stockData'] as $stok)
                        <tr>
                            <td class="pl-4 align-middle font-weight-bold">{{ $stok->nama_lokasi }}</td>
                            <td class="align-middle"><span class="text-monospace">{{ $stok->part_code }}</span></td>
                            <td class="align-middle">{{ $stok->part_name }}</td>
                            <td class="text-center align-middle">
                                <h6 class="mb-0 font-weight-bold {{ $stok->total_qty < $stok->stok_minimum ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($stok->total_qty, 0, ',', '.') }}
                                </h6>
                            </td>
                            <td class="text-center align-middle text-muted">{{ number_format($stok->stok_minimum, 0, ',', '.') }}</td>
                            <td class="text-center pr-4 align-middle">
                                @if($stok->total_qty < $stok->stok_minimum)
                                    <span class="badge badge-danger px-2 py-1">KRITIS</span>
                                @else
                                    <span class="badge badge-success px-2 py-1">AMAN</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">Data stok tidak tersedia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap4' });
        $('#select2-ygp').select2({
            theme: 'bootstrap4',
            placeholder: "Ketik Kode atau Nama YGP...",
            allowClear: true,
            ajax: {
                url: "{{ route('admin.ajax.ygp') }}",
                dataType: 'json',
                delay: 300, 
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; },
                cache: true
            }
        });
    });

    const formatRp = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
    const lblCurText = "Periode Filter";
    const lblPrevText = "H-1 Bulan Lalu";

    // 1. Bar Chart OMSET Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetailOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Omset)', data: [{{ $data['totalRetailOmset'] }}, {{ $data['totalPrevRetailOmset'] }}], backgroundColor: ['#28a745', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 2. Bar Chart OMSET Perbandingan SERVICE
    new Chart(document.getElementById('barCompareServiceOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Omset)', data: [{{ $data['totalServiceOmset'] }}, {{ $data['totalPrevServiceOmset'] }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 3. Pie Omset
    new Chart(document.getElementById('pieOmset').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Omset)', 'Service (Omset)'],
            datasets: [{ data: [{{ $data['omsetPie']['retail'] }}, {{ $data['omsetPie']['service'] }}], backgroundColor: ['#28a745', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatRp(ctx.raw) } } } }
    });

    // 4. Bar Omset (Line)
    var ctxOmset = document.getElementById('barOmset').getContext('2d');
    new Chart(ctxOmset, {
        type: 'line', data: {
            labels: {!! json_encode($data['chartLabels']) !!},
            datasets: [
                { label: 'Omset Retail', borderColor: '#28a745', data: {!! json_encode($data['chartRetailOmset']) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 },
                { label: 'Omset Service', borderColor: '#17a2b8', data: {!! json_encode($data['chartServiceOmset']) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID', {notation: "compact"}).format(val); } } } },
            plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ': ' + formatRp(ctx.raw) } } }
        }
    });
</script>
@endpush