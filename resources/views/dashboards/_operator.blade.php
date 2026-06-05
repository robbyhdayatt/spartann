<div class="row mb-3">
    <div class="col-12">
        <h4><i class="fas fa-warehouse text-primary"></i> Dashboard Finansial & Operasional PC</h4>
        <p class="text-muted">User: <strong>{{ Auth::user()->nama }}</strong> | Lokasi: <strong>{{ $data['lokasi']->nama_lokasi }}</strong> | Fokus Kinerja Produk: <b>Engine Additive, Tire Sealant, Injector Cleaner</b></p>
    </div>
</div>

<div class="row">
    {{-- ==================== TASK COUNTER ==================== --}}
    @if(!$data['isPusat'])
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $data['taskCounts']['receiving_po'] }}</h3>
                    <p>Barang dari Pusat (PO)</p>
                </div>
                <div class="icon"><i class="fas fa-truck-loading"></i></div>
                <a href="{{ route('admin.receivings.create') }}" class="small-box-footer">Terima PO <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $data['taskCounts']['putaway'] }}</h3>
                    <p>Pending Putaway</p>
                </div>
                <div class="icon"><i class="fas fa-dolly"></i></div>
                <a href="{{ route('admin.putaway.index') }}" class="small-box-footer">Simpan Rak <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3>POS</h3>
                    <p>Point of Sales</p>
                </div>
                <div class="icon"><i class="fas fa-cash-register"></i></div>
                <a href="{{ route('admin.penjualans.create') }}" class="small-box-footer">Buat Penjualan <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>Import</h3>
                    <p>Import Data Service</p>
                </div>
                <div class="icon"><i class="fas fa-file-import"></i></div>
                    <a href="{{ route('admin.services.index') }}" class="small-box-footer">Proses Import Service <i class="fas fa-arrow-circle-right"></i></a>
                </form>
            </div>
        </div>
    @endif
</div>

@if(!$data['isPusat'])
{{-- METODE GABUNGAN: PERGERAKAN FISIK (QTY) --}}
<h5 class="mt-3 mb-2"><i class="fas fa-box-open text-primary"></i> Pergerakan Fisik Barang (3 Item Fokus)</h5>
<div class="row">
    <div class="col-md-6">
        <div class="card card-outline card-primary shadow-sm border-0">
            <div class="card-header border-0"><h3 class="card-title font-weight-bold"><i class="fas fa-store text-primary mr-1"></i> Unit Terjual (POS)</h3></div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-4 border-right text-center">
                        <span class="text-muted text-xs d-block">MINGGU INI</span>
                        <h4 class="text-primary font-weight-bold mb-0">{{ number_format($data['qty']['penjualan']['minggu'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                    <div class="col-4 border-right text-center">
                        <span class="text-muted text-xs d-block">BULAN INI</span>
                        <h4 class="text-primary font-weight-bold mb-0">{{ number_format($data['qty']['penjualan']['bulan'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                    <div class="col-4 text-center">
                        <span class="text-muted text-xs d-block">TAHUN INI</span>
                        <h4 class="text-primary font-weight-bold mb-0">{{ number_format($data['qty']['penjualan']['tahun'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-outline card-warning shadow-sm border-0">
            <div class="card-header border-0"><h3 class="card-title font-weight-bold"><i class="fas fa-cogs text-warning mr-1"></i> Unit Terpakai (Service)</h3></div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-4 border-right text-center">
                        <span class="text-muted text-xs d-block">MINGGU INI</span>
                        <h4 class="text-warning font-weight-bold mb-0">{{ number_format($data['qty']['service']['minggu'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                    <div class="col-4 border-right text-center">
                        <span class="text-muted text-xs d-block">BULAN INI</span>
                        <h4 class="text-warning font-weight-bold mb-0">{{ number_format($data['qty']['service']['bulan'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                    <div class="col-4 text-center">
                        <span class="text-muted text-xs d-block">TAHUN INI</span>
                        <h4 class="text-warning font-weight-bold mb-0">{{ number_format($data['qty']['service']['tahun'], 0, ',', '.') }} <small>Pcs</small></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== TABEL MONITORING STOK ==================== --}}
<div class="row mt-3">
    <div class="col-12">
        <div class="card card-outline card-danger shadow-sm">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-boxes mr-1"></i> Peringatan Stok Kritis Khusus 3 Barang Fokus ({{ $data['lokasi']->nama_lokasi }})
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.reports.stock-by-warehouse') }}" class="btn btn-tool btn-sm">
                        <i class="fas fa-bars"></i> Lihat Semua Laporan
                    </a>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-striped table-valign-middle">
                    <thead>
                    <tr>
                        <th>Barang / Part</th>
                        <th class="text-center">Stok Saat Ini</th>
                        <th class="text-center">Min. Stok</th>
                        <th class="text-center">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data['stockData'] as $item)
                        <tr>
                            <td>
                                {{ $item->part_name }} <br>
                                <small class="text-muted">{{ $item->part_code }}</small>
                            </td>
                            <td class="text-center font-weight-bold" style="font-size: 1.1em">
                                {{ $item->total_qty }}
                            </td>
                            <td class="text-center text-muted">
                                {{ $item->stok_minimum }}
                            </td>
                            <td class="text-center">
                                @if($item->total_qty < $item->stok_minimum)
                                    <span class="badge badge-danger">LOW / KRITIS</span>
                                @else
                                    <span class="badge badge-success">AMAN</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada data stok untuk 3 barang fokus.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Hanya inisialisasi chart jika user bukan pusat
    @if(!$data['isPusat'])
    const chartData = @json($data['chart']);
    const ctx = document.getElementById('omsetChart').getContext('2d');
    let omsetChart;

    function renderChart(labels, sales, service) {
        if (omsetChart) omsetChart.destroy();
        omsetChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'POS Omset (Rp)', borderColor: '#28a745', backgroundColor: 'transparent', data: sales.omset, borderWidth: 2, tension: 0.2 },
                    { label: 'Service Omset (Rp)', borderColor: '#17a2b8', backgroundColor: 'transparent', data: service.omset, borderWidth: 2, tension: 0.2 }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { 
                                if(value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                if(value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                return 'Rp ' + value; 
                            }
                        }
                    }
                }
            }
        });
    }
    window.updateChart = function(p) { renderChart(chartData.labels[p], chartData.penjualan[p], chartData.service[p]); }
    $(document).ready(function() { updateChart('minggu'); });
    @endif
</script>
@endpush