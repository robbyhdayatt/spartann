<div class="row">
    <div class="col-md-4">
        <div class="card card-widget widget-user-2">
            <div class="widget-user-header bg-primary">
                <div class="widget-user-image">
                    <img class="img-circle elevation-2" src="{{ asset('img/SPARTAN.png') }}" alt="User Avatar">
                </div>
                <h3 class="widget-user-username">{{ Auth::user()->nama }}</h3>
                <h5 class="widget-user-desc">{{ Auth::user()->jabatan->nama_jabatan }}</h5>
            </div>
            <div class="card-footer p-0">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            Target Bulan Ini <span class="float-right badge bg-primary">@rupiah($data['targetAmount'])</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            Pencapaian <span class="float-right badge bg-success">@rupiah($data['achievedAmount'])</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            Estimasi Insentif <span class="float-right badge bg-warning">@rupiah($data['jumlahInsentif'])</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title">Progress Target</h3>
            </div>
            <div class="card-body text-center">
                <input type="text" class="knob" value="{{ round($data['achievementPercentage']) }}" data-width="150" data-height="150" data-fgColor="#39CCCC" data-readonly="true">
                <div class="knob-label">Persentase Pencapaian</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Penjualan Terakhir Anda</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No Faktur</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['recentSales'] as $sale)
                        <tr>
                            <td>{{ $sale->tanggal_jual->format('d-m-Y') }}</td>
                            <td>{{ $sale->nomor_faktur }}</td>
                            <td>{{ $sale->konsumen->nama_konsumen }}</td>
                            <td>@rupiah($sale->total_harga)</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center">Belum ada penjualan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="{{ asset('vendor/jquery-knob/jquery.knob.min.js') }}"></script>
<script>
    $(function () {
        $('.knob').knob();
    })
</script>
@endpush
