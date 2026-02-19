<div class="row mb-3">
    <div class="col-12">
        <h4><i class="fas fa-warehouse"></i> Dashboard Operasional {{ $data['isPusat'] ? 'Gudang Pusat' : 'Part Counter' }}</h4>
        <p>User: <strong>{{ Auth::user()->nama }}</strong> | Lokasi: <strong>{{ $data['lokasi']->nama_lokasi }}</strong></p>
    </div>
</div>

<div class="row">
    
    {{-- ==================== ADMIN DEALER / PART COUNTER ==================== --}}
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
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $data['taskCounts']['incoming_mutation_transit'] }}</h3>
                    <p>Barang dari Dealer Lain</p>
                </div>
                <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                <a href="{{ route('admin.mutation-receiving.index') }}" class="small-box-footer">Proses Mutasi <i class="fas fa-arrow-circle-right"></i></a>
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
    @endif
</div>

@if(!$data['isPusat'])
<div class="row">
    <div class="col-12">
        <div class="callout callout-warning shadow-sm">
            <h5><i class="fas fa-chart-pie text-warning mr-2"></i> Performa Penjualan Barang (Bulan Ini)</h5>
            <p>Total barang keluar melalui <strong>Service</strong> dan <strong>Penjualan Langsung</strong> di dealer ini:</p>
            <h2 class="text-dark font-weight-bold">
                {{ number_format($data['totalItemsSoldMonth'], 0, ',', '.') }} 
                <span style="font-size: 1rem; color: #666;">Unit/Pcs</span>
            </h2>
        </div>
    </div>
</div>
@endif

{{-- TABEL MONITORING STOK --}}
@if(!$data['isPusat'])
<div class="row mt-3">
    <div class="col-12">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-boxes mr-1"></i> Monitoring Stok Dealer ({{ $data['lokasi']->nama_lokasi }})
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.reports.stock-by-warehouse') }}" class="btn btn-tool btn-sm">
                        <i class="fas fa-bars"></i> Lihat Semua
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
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
                            <td colspan="4" class="text-center text-muted py-4">Belum ada data stok.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif