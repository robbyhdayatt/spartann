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
        <h4 class="text-dark"><i class="fas fa-store-alt text-primary mr-2"></i> Dashboard Kepala Cabang (KC)</h4>
        <p class="text-muted">User: <strong>{{ Auth::user()->nama ?? Auth::user()->username }}</strong> | Lokasi: <strong>{{ $data['lokasi']->nama_lokasi ?? 'Unknown' }}</strong></p>
    </div>
</div>

{{-- FORM FILTER GANDA (TANPA PILIHAN DEALER KARENA SUDAH DILOCK) --}}
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body py-3">
                <form action="{{ route('admin.home') }}" method="GET">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <label class="text-xs text-muted">Tanggal Awal</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $data['filter']['startDate'] }}">
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <label class="text-xs text-muted">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $data['filter']['endDate'] }}">
                        </div>
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
                        <div class="col-md-3 mb-2 mb-md-0">
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
<h5 class="text-primary font-weight-bold mb-3"><i class="fas fa-money-check-alt mr-2"></i> SECTION 1: FINANCIAL METRICS (OMSET)</h5>

{{-- METRIK KESEHATAN LABA (OMSET) --}}
<div class="row">
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-success mb-1">Rp {{ number_format($data['grandTotalOmset'], 0, ',', '.') }}</h3>
                <p class="text-muted font-weight-bold mb-0">Total Pendapatan (Omset Netto)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box bg-white border shadow-sm">
            <div class="inner text-center py-4">
                <h3 class="text-info mb-1">Rp {{ number_format($data['grandTotalLaba'], 0, ',', '.') }}</h3>
                <p class="text-muted font-weight-bold mb-0">Laba Kotor (Setelah Diskon)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box {{ $data['grossProfitMargin'] > 15 ? 'bg-primary' : 'bg-warning' }} shadow-sm">
            <div class="inner text-center py-4 text-white">
                <h3 class="mb-1">{{ $data['grossProfitMargin'] }} <sup style="font-size: 20px">%</sup></h3>
                <p class="font-weight-bold mb-0">Gross Profit Margin (Netto)</p>
            </div>
            <div class="icon"><i class="fas fa-percent" style="opacity: 0.3;"></i></div>
        </div>
    </div>
</div>

{{-- OMSET CHARTS --}}
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

{{-- TOP PART LARIS --}}
<div class="row mt-3">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-fire text-danger mr-2"></i> Top 5 Part Paling Laris (Pcs)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Qty</th></tr></thead>
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

    // 3. Pie Qty
    new Chart(document.getElementById('pieQty').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Qty)', 'Service (Qty)'],
            datasets: [{ data: [{{ $data['qtyPie']['retail'] }}, {{ $data['qtyPie']['service'] }}], backgroundColor: ['#ffc107', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatQty(ctx.raw) } } } }
    });

    // 4. Bar Qty (Line)
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