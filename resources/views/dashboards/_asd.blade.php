<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-chart-line mr-2 text-primary"></i> Dashboard Area Service Development (ASD)</h4>
        <p class="text-muted">Pantauan Performa Service & Penjualan Jaringan Dealer</p>
    </div>
</div>

{{-- WIDGETS KASIR STYLE --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info shadow-sm">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Part Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <a href="{{ route('admin.penjualans.index') }}" class="small-box-footer">Lihat Transaksi <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success shadow-sm">
            <div class="inner">
                <h3>{{ $data['serviceToday'] }}</h3>
                <p>Unit Service Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-wrench"></i></div>
            <a href="{{ route('admin.services.index') }}" class="small-box-footer">Lihat Service <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary shadow-sm">
            <div class="inner">
                <h3>{{ $data['salesWeek'] }}</h3>
                <p>Penjualan Part (7 Hari Terakhir)</p>
            </div>
            <div class="icon"><i class="fas fa-chart-bar"></i></div>
            <a href="{{ route('admin.penjualans.index') }}" class="small-box-footer">Lihat Transaksi <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="small-box bg-teal shadow-sm">
            <div class="inner">
                <h3>{{ $data['serviceWeek'] }}</h3>
                <p>Unit Service (7 Hari Terakhir)</p>
            </div>
            <div class="icon"><i class="fas fa-cogs"></i></div>
            <a href="{{ route('admin.services.index') }}" class="small-box-footer">Lihat Service <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    {{-- GRAFIK TREN SERVICE & PENJUALAN (30 HARI) --}}
    <div class="col-md-7">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header border-0">
                <h3 class="card-title"><i class="fas fa-chart-area mr-1"></i> Tren Transaksi (30 Hari Terakhir)</h3>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="asdTrendChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- STOK JARINGAN DEALER --}}
    <div class="col-md-5">
        <div class="card card-outline card-danger shadow-sm">
            <div class="card-header border-0">
                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1"></i> Monitoring Stok Dealer</h3>
            </div>
            <div class="card-body table-responsive p-0" style="max-height: 340px;">
                <table class="table table-sm table-striped table-head-fixed text-nowrap">
                    <thead>
                        <tr>
                            <th>Dealer</th>
                            <th>Kode Part</th>
                            <th class="text-right">Sisa</th>
                            <th class="text-right">Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['stockData'] as $stok)
                        <tr class="{{ $stok->total_qty < $stok->stok_minimum ? 'table-danger' : '' }}">
                            <td class="align-middle">
                                <span class="d-block text-bold">{{ $stok->nama_lokasi }}</span>
                            </td>
                            <td class="align-middle">{{ $stok->part_code }}</td>
                            <td class="text-right align-middle font-weight-bold {{ $stok->total_qty < $stok->stok_minimum ? 'text-danger' : '' }}">
                                {{ $stok->total_qty }}
                            </td>
                            <td class="text-right align-middle text-muted">{{ $stok->stok_minimum }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-check-circle text-success mb-2" style="font-size: 1.5rem;"></i><br>
                                Stok jaringan dealer aman.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- SCRIPT UNTUK CHART.JS --}}
@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        var ctx = document.getElementById('asdTrendChart').getContext('2d');
        var asdTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($data['chartLabels']) !!},
                datasets: [
                    {
                        label: 'Unit Service',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        data: {!! json_encode($data['serviceChartData']) !!},
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Penjualan Part',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                        data: {!! json_encode($data['salesChartData']) !!},
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    });
</script>
@endpush