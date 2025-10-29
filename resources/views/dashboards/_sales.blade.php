<div class="row">
    <div class="col-md-12">
        <div class="callout callout-info">
            <h5><i class="fas fa-bullseye"></i> Performa Penjualan Anda Bulan Ini</h5>
            <p>Selamat datang, <strong>{{ Auth::user()->nama }}</strong>! Berikut adalah ringkasan kinerja penjualan Anda untuk bulan {{ now()->translatedFormat('F Y') }}.</p>
        </div>
    </div>
</div>

<div class="row">
    {{-- Info Box Target --}}
    <div class="col-md-6 col-lg-3">
        <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-flag-checkered"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Target Bulan Ini</span>
                <span class="info-box-number">Rp {{ number_format($data['targetAmount'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    {{-- Info Box Pencapaian --}}
    <div class="col-md-6 col-lg-3">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-trophy"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Penjualan Tercapai</span>
                <span class="info-box-number">Rp {{ number_format($data['achievedAmount'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    {{-- Info Box Persentase --}}
   <div class="col-md-6 col-lg-3">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-percentage"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pencapaian Target</span>

                {{-- ++ PERBAIKAN PADA PROGRESS BAR ++ --}}
                <div class="progress" style="height: 20px; background-color: #e9ecef;">
                    {{-- 1. Tambah kelas bg-info --}}
                    {{-- 2. Batasi lebar visual maks 100% menggunakan min() --}}
                    <div class="progress-bar bg-info"
                         role="progressbar"
                         style="width: {{ min($data['achievementPercentage'], 100) }}%;"
                         aria-valuenow="{{ $data['achievementPercentage'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">

                        {{-- 3. Tambah kelas text-dark agar teks terlihat --}}
                        <strong class="text-dark">{{ number_format($data['achievementPercentage'], 1) }}%</strong>
                    </div>
                </div>
                {{-- ++ AKHIR PERBAIKAN ++ --}}

            </div>
        </div>
    </div>

        {{-- Info Box Insentif --}}
        <div class="col-md-6 col-lg-3">
            <div class="info-box bg-gradient-warning">
                <span class="info-box-icon"><i class="fas fa-gift"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Perolehan Insentif</span>
                    {{-- Ini sekarang akan menampilkan nilai yang dihitung real-time --}}
                    <span class="info-box-number">Rp {{ number_format($data['incentiveAmount'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> 5 Transaksi Penjualan Terakhir Anda</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Nomor Invoice</th>
                            <th>Tanggal</th>
                            <th>Konsumen</th>
                            <th>Total Harga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['recentSales'] as $sale)
                            <tr>
                                <td>{{ $sale->nomor_invoice }}</td>
                                <td>{{ \Carbon\Carbon::parse($sale->tanggal_jual)->format('d M Y') }}</td>
                                <td>{{ $sale->konsumen->nama_konsumen }}</td>
                                <td>Rp {{ number_format($sale->total_harga, 0, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('admin.penjualans.show', $sale->id) }}" class="btn btn-xs btn-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">Anda belum memiliki transaksi penjualan bulan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
