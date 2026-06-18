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
        <h4 class="text-dark"><i class="fas fa-warehouse text-primary mr-2"></i> Dashboard Operasional PC</h4>
        <p class="text-muted">User: <strong>{{ Auth::user()->nama ?? Auth::user()->username }}</strong> | Lokasi: <strong>{{ $data['lokasi']->nama_lokasi ?? 'Dealer' }}</strong></p>
    </div>
</div>

<div class="row">
    {{-- WIDGET PENDING RECEIVE --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info shadow-sm">
            <div class="inner">
                <h3>{{ $data['pendingReceive'] }}</h3>
                <p>Pending Receive (PO Pusat)</p>
            </div>
            <div class="icon"><i class="fas fa-truck-loading"></i></div>
            <a href="{{ route('admin.receivings.create') }}" class="small-box-footer">Terima Barang <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    
    {{-- [TAMBAHAN] WIDGET PENDING PUTAWAY --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary shadow-sm">
            <div class="inner">
                <h3>{{ $data['pendingPutaway'] }}</h3>
                <p>Pending Putaway</p>
            </div>
            <div class="icon"><i class="fas fa-dolly"></i></div>
            <a href="{{ route('admin.putaway.index') }}" class="small-box-footer">Simpan Rak <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

{{-- FORM FILTER --}}
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

<hr class="mt-2 mb-4">
<h5 class="text-danger font-weight-bold mb-3"><i class="fas fa-boxes mr-2"></i> VOLUME METRICS (PHYSICAL QTY)</h5>

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

{{-- QTY COMPARISON CHARTS --}}
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

{{-- TOP QTY & STOK DEALER --}}
<div class="row mt-3">
    <div class="col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-fire text-danger mr-2"></i> Top 5 Part Laris</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Qty</th></tr></thead>
                    <tbody>
                        @forelse($data['topItemsQty'] as $item)
                        <tr><td class="pl-4 align-middle font-weight-bold text-truncate" style="max-width:200px;">{{ $item->name }}</td><td class="text-right pr-4"><h6 class="text-danger font-weight-bold mb-0">{{ number_format($item->qty, 0, ',', '.') }}</h6></td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card card-outline card-danger shadow-sm border-0 h-100">
            <div class="card-header bg-white pb-2">
                <h3 class="card-title font-weight-bold"><i class="fas fa-exclamation-triangle text-danger mr-2"></i> Monitoring Stok Dealer ({{ $data['lokasi']->nama_lokasi ?? '-' }})</h3>
            </div>
            <div class="card-body table-responsive p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-striped table-hover table-head-fixed text-nowrap">
                    <thead class="bg-light">
                        <tr>
                            <th class="pl-4">Kode Part</th>
                            <th>Nama Barang</th>
                            <th class="text-center">Sisa Stok</th>
                            <th class="text-center">Min. Stok</th>
                            <th class="text-center pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['stockData'] as $stok)
                        <tr class="{{ $stok->total_qty < $stok->stok_minimum ? 'table-danger' : '' }}">
                            <td class="align-middle pl-4"><span class="text-monospace">{{ $stok->part_code }}</span></td>
                            <td class="align-middle">{{ $stok->part_name }}</td>
                            <td class="text-center align-middle font-weight-bold {{ $stok->total_qty < $stok->stok_minimum ? 'text-danger' : 'text-success' }}">
                                {{ $stok->total_qty }}
                            </td>
                            <td class="text-center align-middle text-muted">{{ $stok->stok_minimum }}</td>
                            <td class="text-center pr-4 align-middle">
                                @if($stok->total_qty < $stok->stok_minimum)
                                    <span class="badge badge-danger px-2 py-1">KRITIS</span>
                                @else
                                    <span class="badge badge-success px-2 py-1">AMAN</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Stok dealer aman / belum ada data.</td>
                        </tr>
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

    const formatQty = (value) => new Intl.NumberFormat('id-ID').format(value) + ' Pcs';
    const lblCurText = "Periode Filter";
    const lblPrevText = "H-1 Bulan Lalu";

    // 1. Bar Chart QTY Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetail').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Qty)', data: [{{ $data['totalRetailQty'] }}, {{ $data['totalPrevRetailQty'] }}], backgroundColor: ['#ffc107', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
    });

    // 2. Bar Chart QTY Perbandingan SERVICE
    new Chart(document.getElementById('barCompareService').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Qty)', data: [{{ $data['totalServiceQty'] }}, {{ $data['totalPrevServiceQty'] }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
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