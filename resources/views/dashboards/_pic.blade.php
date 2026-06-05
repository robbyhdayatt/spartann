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
        <h4 class="text-dark"><i class="fas fa-briefcase mr-2"></i> Hybrid Analytical Dashboard (PIC)</h4>
        <p class="text-muted">Analisis Finansial & Volume Terkonsolidasi: <b>Part Non-YGP & YGP Jaringan Dealer</b></p>
    </div>
</div>

{{-- ROW 1: APPROVAL ALERTS --}}
@if($data['totalPending'] > 0)
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning border-left-warning shadow-sm">
            <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian! Ada {{ $data['totalPending'] }} Dokumen Menunggu Persetujuan Anda.</h5>
            <div class="mt-2">
                @if($data['pendingPO'] > 0)
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-file-invoice mr-1"></i> {{ $data['pendingPO'] }} PO Dealer</a>
                @endif
                @if($data['pendingMutasi'] > 0)
                    <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-exchange-alt mr-1"></i> {{ $data['pendingMutasi'] }} Mutasi Stok</a>
                @endif
                @if($data['pendingAdjustment'] > 0)
                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-warning btn-sm"><i class="fas fa-sliders-h mr-1"></i> {{ $data['pendingAdjustment'] }} Penyesuaian Stok</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

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
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-primary font-weight-bold">Filter Non-YGP</label>
                            <select name="barang_id" class="form-control form-control-sm select2">
                                <option value="all">Semua Non-YGP</option>
                                @foreach($data['daftarBarang'] as $brg)
                                    <option value="{{ $brg->id }}" {{ $data['filter']['filterNonYgp'] == $brg->id ? 'selected' : '' }}>{{ $brg->part_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2 mb-md-0">
                            <label class="text-xs text-danger font-weight-bold">Filter YGP (AJAX)</label>
                            <select name="part_code" id="select2-ygp" class="form-control form-control-sm">
                                @if($data['filter']['filterYgp'] === 'all')
                                    <option value="all" selected>Semua YGP</option>
                                @else
                                    <option value="all">Semua YGP</option>
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
<h5 class="text-primary font-weight-bold mb-3"><i class="fas fa-money-check-alt mr-2"></i> SECTION 1: FINANCIAL METRICS (OMSET)</h5>

{{-- METRIK KESEHATAN LABA (OMSET) --}}
<div class="row">
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-success mb-1">Rp {{ number_format($data['grandTotalOmset'], 0, ',', '.') }}</h3>
                <p class="text-muted font-weight-bold mb-0">Total Pendapatan (Omset)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-info mb-1">Rp {{ number_format($data['grandTotalLaba'], 0, ',', '.') }}</h3>
                <p class="text-muted font-weight-bold mb-0">Total Laba Kotor (Gross Profit)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box {{ $data['grossProfitMargin'] > 15 ? 'bg-primary' : 'bg-warning' }} shadow-sm">
            <div class="inner text-center py-4 text-white">
                <h3 class="mb-1">{{ $data['grossProfitMargin'] }} <sup style="font-size: 20px">%</sup></h3>
                <p class="font-weight-bold mb-0">Gross Profit Margin</p>
            </div>
            <div class="icon"><i class="fas fa-percent" style="opacity: 0.3;"></i></div>
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

{{-- OMSET CHARTS --}}
<div class="row mt-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-secondary mr-2"></i> Komposisi Omset</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="pieOmset"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-line text-success mr-2"></i> Tren Omset Harian</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barOmset"></canvas></div></div>
        </div>
    </div>
</div>

{{-- TOP OMSET --}}
<div class="row mt-3">
    <div class="col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-medal text-warning mr-2"></i> Top 5 Part Penghasil Omset</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Rupiah</th></tr></thead>
                    <tbody>
                        @forelse($data['topItemsOmset'] as $item)
                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $item->name }}</td><td class="text-right pr-4"><h6 class="text-success font-weight-bold mb-0">Rp {{ number_format($item->omset, 0, ',', '.') }}</h6></td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-store text-danger mr-2"></i> Top 5 Dealer (Omset)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Dealer</th><th class="text-right pr-4">Rupiah</th></tr></thead>
                    <tbody>
                        @forelse($data['topCabangOmset'] as $cab)
                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $cab->nama_lokasi }}</td><td class="text-right pr-4"><h6 class="text-primary font-weight-bold mb-0">Rp {{ number_format($cab->omset, 0, ',', '.') }}</h6></td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<hr class="mt-5">
<h5 class="text-danger font-weight-bold mb-3"><i class="fas fa-boxes mr-2"></i> SECTION 2: VOLUME METRICS (PHYSICAL QTY)</h5>

{{-- METRIK VOLUME (QTY) --}}
<div class="row">
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-dark mb-1">{{ number_format($data['totalRetailQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                <p class="text-muted font-weight-bold mb-0">Qty Terjual Kasir (Retail)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-dark mb-1">{{ number_format($data['totalServiceQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                <p class="text-muted font-weight-bold mb-0">Qty Terpasang Bengkel (Service)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-danger shadow-sm">
            <div class="inner text-center py-4 text-white">
                <h3 class="mb-1">{{ number_format($data['grandTotalQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                <p class="font-weight-bold mb-0">Grand Total Part Keluar</p>
            </div>
            <div class="icon"><i class="fas fa-cogs" style="opacity: 0.3;"></i></div>
        </div>
    </div>
</div>

{{-- QTY COMPARISON CHARTS (CURRENT VS PREVIOUS) --}}
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1">
                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-left text-warning mr-2"></i> Perbandingan Retail (Qty)</h3>
                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
            </div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareRetail"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1">
                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-right text-info mr-2"></i> Perbandingan Service (Qty)</h3>
                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
            </div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareService"></canvas></div></div>
        </div>
    </div>
</div>

{{-- QTY CHARTS --}}
<div class="row mt-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-secondary mr-2"></i> Komposisi Qty Part</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="pieQty"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-bar text-danger mr-2"></i> Tren Qty Keluar Harian</h3></div>
            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barQty"></canvas></div></div>
        </div>
    </div>
</div>

{{-- TOP QTY --}}
<div class="row mt-3">
    <div class="col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-fire text-danger mr-2"></i> Top 5 Part Paling Laris (Pcs)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Qty Keluar</th></tr></thead>
                    <tbody>
                        @forelse($data['topItemsQty'] as $item)
                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $item->name }}</td><td class="text-right pr-4"><h6 class="text-danger font-weight-bold mb-0">{{ number_format($item->qty, 0, ',', '.') }} Pcs</h6></td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-truck-loading text-dark mr-2"></i> Top 5 Dealer (Volume Qty)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Dealer</th><th class="text-right pr-4">Qty</th></tr></thead>
                    <tbody>
                        @forelse($data['topCabangQty'] as $cab)
                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $cab->nama_lokasi }}</td><td class="text-right pr-4"><h6 class="text-dark font-weight-bold mb-0">{{ number_format($cab->qty, 0, ',', '.') }} Pcs</h6></td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
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
    const formatQty = (value) => new Intl.NumberFormat('id-ID').format(value) + ' Pcs';

    const lblCurText = "Periode Filter";
    const lblPrevText = "H-1 Bulan Lalu";

    // 1. Pie Omset
    new Chart(document.getElementById('pieOmset').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Omset)', 'Service (Omset)'],
            datasets: [{ data: [{{ $data['omsetPie']['retail'] }}, {{ $data['omsetPie']['service'] }}], backgroundColor: ['#28a745', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatRp(ctx.raw) } } } }
    });

    // 2. Bar Omset (Line)
    new Chart(document.getElementById('barOmset').getContext('2d'), {
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

    // 3. Bar Chart OMSET Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetailOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Omset)', data: [{{ $data['totalRetailOmset'] }}, {{ $data['totalPrevRetailOmset'] }}], backgroundColor: ['#28a745', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 4. Bar Chart OMSET Perbandingan SERVICE
    new Chart(document.getElementById('barCompareServiceOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Omset)', data: [{{ $data['totalServiceOmset'] }}, {{ $data['totalPrevServiceOmset'] }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 5. Bar Chart QTY Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetail').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Qty)', data: [{{ $data['totalRetailQty'] }}, {{ $data['totalPrevRetailQty'] }}], backgroundColor: ['#ffc107', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
    });

    // 6. Bar Chart QTY Perbandingan SERVICE
    new Chart(document.getElementById('barCompareService').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Qty)', data: [{{ $data['totalServiceQty'] }}, {{ $data['totalPrevServiceQty'] }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
    });

    // 7. Pie Qty
    new Chart(document.getElementById('pieQty').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Qty)', 'Service (Qty)'],
            datasets: [{ data: [{{ $data['qtyPie']['retail'] }}, {{ $data['qtyPie']['service'] }}], backgroundColor: ['#ffc107', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatQty(ctx.raw) } } } }
    });

    // 8. Bar Qty (Line)
    new Chart(document.getElementById('barQty').getContext('2d'), {
        type: 'line', data: {
            labels: {!! json_encode($data['chartLabels']) !!},
            datasets: [
                { label: 'Qty Retail', borderColor: '#ffc107', data: {!! json_encode($data['chartRetailQty']) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 },
                { label: 'Qty Service', borderColor: '#17a2b8', data: {!! json_encode($data['chartServiceQty']) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ': ' + formatQty(ctx.raw) } } }
        }
    });
</script>
@endpush