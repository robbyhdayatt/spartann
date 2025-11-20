<div class="row">
    {{-- Widget 1: Dinamis (Penjualan Hari Ini / PO Pending) --}}
    <div class="col-lg-4 col-6">
        <div class="small-box {{ $data['widget1Color'] }}">
            <div class="inner">
                <h3>{{ $data['widget1Value'] }}</h3>
                <p>{{ $data['widget1Title'] }}</p>
            </div>
            <div class="icon"><i class="{{ $data['widget1Icon'] }}"></i></div>
            <a href="{{ $data['widget1Route'] }}" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    {{-- Widget 2: Total Nilai Stok (Global) --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>Rp {{ number_format($data['stockValue'], 0, ',', '.') }}</h3>
                <p>Total Nilai Aset Stok (Global)</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <a href="{{ route('admin.reports.inventory-value') }}" class="small-box-footer">Lihat Laporan <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    {{-- Widget 3: Stok Kritis Count --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ count($data['criticalStockParts']) }} Item</h3>
                <p>Stok di Bawah Minimum</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <a href="#critical-section" class="small-box-footer">Lihat Bawah <i class="fas fa-arrow-down"></i></a>
        </div>
    </div>
</div>

<div class="row">
    {{-- Grafik Penjualan (Hanya jika ada datanya) --}}
    @if(!empty($data['salesChartData']) && count($data['salesChartData']) > 0)
    <div class="col-lg-8">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Tren Penjualan (30 Hari)</h3>
            </div>
            <div class="card-body">
                <canvas id="salesChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
    @else
    {{-- Jika grafik kosong (misal SMD), perlebar tabel stok kritis --}}
    <div class="col-lg-12">
        <div class="alert alert-info">
            <h5><i class="icon fas fa-info"></i> Informasi</h5>
            Grafik penjualan tidak ditampilkan untuk peran Anda atau belum ada data.
        </div>
    </div>
    @endif

    {{-- Tabel Stok Kritis --}}
    <div class="{{ (!empty($data['salesChartData']) && count($data['salesChartData']) > 0) ? 'col-lg-4' : 'col-lg-12' }}" id="critical-section">
        <div class="card card-danger card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-boxes"></i> Stok Kritis (Global)</h3>
                <div class="card-tools">
                    <span class="badge badge-warning">{{ count($data['criticalStockParts']) }} Item</span>
                </div>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 340px;">
                <table class="table table-sm table-head-fixed text-nowrap">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="text-center">Stok / Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['criticalStockParts'] as $part)
                        <tr>
                            <td>
                                <strong>{{ $part->part_name }}</strong>
                                <br><small class="text-muted">{{ $part->part_code }} {{ $part->merk ? '- '.$part->merk : '' }}</small>
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge badge-danger" style="font-size: 1rem;">
                                    {{ $part->total_stock }} / {{ $part->stok_minimum }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center p-3">Aman. Tidak ada stok kritis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if(!empty($data['salesChartData']) && count($data['salesChartData']) > 0)
@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var ctx = document.getElementById('salesChart').getContext('2d');
    var salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($data['salesChartLabels']) !!},
            datasets: [{
                label: 'Total Omset (Rp)',
                data: {!! json_encode($data['salesChartData']) !!},
                backgroundColor: 'rgba(60,141,188,0.2)',
                borderColor: 'rgba(60,141,188,1)',
                pointRadius: 3,
                pointBackgroundColor: '#3b8bba',
                pointBorderColor: 'rgba(60,141,188,1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(value); }
                    }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>
@endpush
@endif
