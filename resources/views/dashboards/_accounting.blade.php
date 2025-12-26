<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-calculator mr-2"></i> Financial Dashboard (Accounting)</h4>
        <p class="text-muted">Laporan Keuangan Global & Valuasi Aset</p>
    </div>
</div>

<div class="row">
    {{-- A. NILAI ASET (Selling In) --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-info shadow-sm">
            <div class="inner">
                <h3>Rp {{ number_format($data['inventoryAssetValue'], 0, ',', '.') }}</h3>
                <p>Total Aset Inventory (Global)</p>
            </div>
            <div class="icon"><i class="fas fa-cubes"></i></div>
            <div class="small-box-footer" title="Dihitung berdasarkan harga Selling Out">
                Basis: Selling Out <i class="fas fa-info-circle"></i>
            </div>
        </div>
    </div>

    {{-- B. OMSET (Retail) --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-success shadow-sm">
            <div class="inner">
                <h3>Rp {{ number_format($data['revenueThisMonth'], 0, ',', '.') }}</h3>
                <p>Omset Penjualan (Bulan Ini)</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            <div class="small-box-footer" title="Total harga jual ke konsumen (Retail)">
                Basis: Retail Price <i class="fas fa-info-circle"></i>
            </div>
        </div>
    </div>

    {{-- C. PROFIT (Retail - Selling Out) --}}
    <div class="col-lg-4 col-12">
        <div class="small-box bg-primary shadow-sm">
            <div class="inner">
                <h3>Rp {{ number_format($data['profitThisMonth'], 0, ',', '.') }}</h3>
                <p>Gross Profit (Bulan Ini)</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="small-box-footer" title="Omset Retail dikurangi Modal Selling Out">
                Margin: Retail - Selling Out <i class="fas fa-info-circle"></i>
            </div>
        </div>
    </div>
</div>

{{-- GRAFIK & TABEL (Sama seperti sebelumnya) --}}
<div class="row">
    <div class="col-lg-8">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-area mr-1"></i> Tren Penjualan (30 Hari)</h3>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        {{-- Tabel Transaksi Terakhir --}}
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-1"></i> Transaksi Terakhir</h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Faktur</th><th>Total</th></tr></thead>
                    <tbody>
                        @forelse($data['recentTransactions'] as $trx)
                            <tr>
                                <td>{{ $trx->nomor_faktur }}<br><small>{{ $trx->lokasi->kode_lokasi ?? '-' }}</small></td>
                                <td class="text-right font-weight-bold">Rp {{ number_format($trx->total_harga, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center">Nihil.</td></tr>
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
$(function () {
    var ctx = $('#salesTrendChart').get(0).getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels  : {!! json_encode($data['chartLabels']) !!},
            datasets: [{
                label: 'Total Penjualan (Rp)',
                backgroundColor: 'rgba(40,167,69,0.1)',
                borderColor: '#28a745',
                data: {!! json_encode($data['chartData']) !!},
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                y: {
                    ticks: { callback: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(val); } }
                }
            }
        }
    });
});
</script>
@endpush