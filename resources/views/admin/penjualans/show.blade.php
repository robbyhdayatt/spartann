@extends('adminlte::page')

@section('title', 'Detail Penjualan')

@section('content_header')
    <h1>Detail Faktur Penjualan</h1>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card card-outline card-success">
            <div class="card-header d-print-none">
                <h3 class="card-title">
                    Nomor: <strong>{{ $penjualan->nomor_faktur }}</strong>
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.penjualans.index') }}" class="btn btn-default btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <a href="{{ route('admin.penjualans.print', $penjualan) }}" target="_blank" class="btn btn-default btn-sm">
                        <i class="fas fa-print"></i> Print
                    </a>
                    <a href="{{ route('admin.penjualans.pdf', $penjualan) }}" class="btn btn-danger btn-sm">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                
                {{-- Header Invoice --}}
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h5 class="text-success"><i class="fas fa-store mr-1"></i> SPARTAN PARTS</h5>
                        <address>
                            <strong>{{ $penjualan->lokasi->nama_lokasi ?? 'Pusat' }}</strong><br>
                            {{ $penjualan->lokasi->alamat ?? '-' }}<br>
                            Sales: {{ $penjualan->sales->nama ?? '-' }}
                        </address>
                    </div>
                    <div class="col-sm-6 text-right">
                        <h5>Kepada:</h5>
                        <address>
                            <strong>{{ $penjualan->konsumen->nama_konsumen }}</strong><br>
                            {{ $penjualan->konsumen->alamat ?? '' }}<br>
                            Telp: {{ $penjualan->konsumen->telepon ?? '-' }}<br>
                            Tanggal: {{ $penjualan->tanggal_jual->format('d/m/Y') }}
                        </address>
                    </div>
                </div>

                {{-- Tabel Barang --}}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="bg-success text-white">
                            <tr>
                                <th>#</th>
                                <th>Barang</th>
                                <th>Kode Part</th>
                                <th class="text-center">Rak</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Harga Satuan</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $mergedDetails = $penjualan->details->groupBy('barang_id'); 
                                $counter = 1;
                            @endphp
                            
                            {{-- Kita grouping tampilan agar jika 1 barang diambil dari 2 rak berbeda, tampilannya tetap rapi --}}
                            @foreach($mergedDetails as $barangId => $details)
                                @php 
                                    $firstItem = $details->first(); 
                                    $totalQty = $details->sum('qty_jual');
                                    $totalSub = $details->sum('subtotal');
                                    $raks = $details->pluck('rak.nama_rak')->unique()->join(', ');
                                @endphp
                                <tr>
                                    <td>{{ $counter++ }}</td>
                                    <td>{{ $firstItem->barang->part_name }}</td>
                                    <td>{{ $firstItem->barang->part_code }}</td>
                                    <td class="text-center"><small class="badge badge-light border">{{ $raks }}</small></td>
                                    <td class="text-center font-weight-bold">{{ $totalQty }}</td>
                                    <td class="text-right">Rp {{ number_format($firstItem->harga_jual, 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($totalSub, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-right">Subtotal:</th>
                                <td class="text-right">Rp {{ number_format($penjualan->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            @if($penjualan->diskon > 0)
                            <tr>
                                <th colspan="6" class="text-right text-danger">Diskon ({{ $penjualan->keterangan_diskon }}):</th>
                                <td class="text-right text-danger">- Rp {{ number_format($penjualan->diskon, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($penjualan->pajak > 0)
                            <tr>
                                <th colspan="6" class="text-right">PPN (11%):</th>
                                <td class="text-right">Rp {{ number_format($penjualan->pajak, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr class="bg-light">
                                <th colspan="6" class="text-right"><h4>Grand Total:</h4></th>
                                <td class="text-right"><h4 class="text-success font-weight-bold">Rp {{ number_format($penjualan->total_harga, 0, ',', '.') }}</h4></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Footer Note --}}
                <div class="row mt-4">
                    <div class="col-12 text-muted text-center small">
                        <p>Terima kasih telah berbelanja di SPARTAN. Barang yang sudah dibeli tidak dapat ditukar/dikembalikan kecuali ada perjanjian sebelumnya.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@stop