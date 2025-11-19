{{-- Baris untuk Info Box --}}
<div class="row">
    <div class="col-lg-4 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-cash-register"></i></div>
            <a href="{{ route('admin.penjualans.index') }}" class="small-box-footer">Info lebih <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-4 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>Rp {{ number_format($data['stockValue'], 0, ',', '.') }}</h3>
                <p>Total Nilai Stok</p>
            </div>
            <div class="icon"><i class="fas fa-boxes"></i></div>
            <a href="{{ route('admin.reports.inventory-value') }}" class="small-box-footer">Info lebih <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
     <div class="col-lg-4 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ count($data['criticalStockParts']) }}</h3>
                <p>Item Stok Kritis</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <a href="#" class="small-box-footer">Info lebih <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

{{-- Baris untuk Konten Utama (Grafik dan List) --}}
<div class="row">
    {{-- Kolom Kiri - Grafik (Tetap sama) --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Grafik Penjualan (30 Hari Terakhir)</h3>
            </div>
            <div class="card-body">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan - Stok Kritis --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stok Kritis (di Bawah Minimum)</h3>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm">
                    <tbody>
                        @forelse($data['criticalStockParts'] as $part)
                        <tr>
                            <td>
                                {{-- PERUBAHAN NAMA KOLOM --}}
                                {{ $part->part_name }}
                                <br>
                                <small class="text-muted">{{ $part->part_code }}</small>
                            </td>
                            <td class="align-middle">
                                <span class="badge badge-danger">{{ $part->total_stock }} / {{ $part->stok_minimum }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center p-2">Tidak ada stok kritis.</td></tr>
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
    var ctx = document.getElementById('salesChart').getContext('2d');
    var salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($data['salesChartLabels']) !!},
            datasets: [{
                label: 'Total Penjualan (Rp)',
                data: {!! json_encode($data['salesChartData']) !!},
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: { // Perubahan untuk Chart.js v3+
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });
</script>
@endpush
