@extends('adminlte::page')

@section('title', 'Detail Penerimaan')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Detail Penerimaan <small class="text-muted">{{ $receiving->nomor_penerimaan }}</small></h1>
        <a href="{{ route('admin.receivings.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Kembali
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        {{-- Kiri: Informasi Dokumen --}}
        <div class="col-md-8">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-invoice text-primary mr-2"></i> Informasi Dokumen
                    </h3>
                    <div class="card-tools">
                        <span class="badge {{ $receiving->status_class }}">{{ $receiving->status_badge }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row invoice-info">
                        <div class="col-sm-4 invoice-col">
                            <strong class="text-secondary">Penerima (Gudang):</strong>
                            <address class="mt-2">
                                <strong>{{ $receiving->lokasi->nama_lokasi ?? 'N/A' }}</strong><br>
                                {{ $receiving->lokasi->alamat ?? '-' }}
                            </address>
                        </div>
                        <div class="col-sm-4 invoice-col">
                            <strong class="text-secondary">Sumber Barang:</strong>
                            <address class="mt-2">
                                @if($receiving->purchaseOrder->supplier)
                                    <strong>{{ $receiving->purchaseOrder->supplier->nama_supplier }}</strong><br>
                                    {{ $receiving->purchaseOrder->supplier->alamat ?? '' }}
                                @elseif($receiving->purchaseOrder->sumberLokasi)
                                    <strong>{{ $receiving->purchaseOrder->sumberLokasi->nama_lokasi }}</strong><br>
                                    (Transfer Internal)
                                @else
                                    -
                                @endif
                            </address>
                        </div>
                        <div class="col-sm-4 invoice-col">
                            <div class="bg-light p-2 rounded">
                                <b>No. Receive:</b> {{ $receiving->nomor_penerimaan }}<br>
                                <b>Referensi PO:</b> 
                                <a href="{{ route('admin.purchase-orders.show', $receiving->purchase_order_id) }}">{{ $receiving->purchaseOrder->nomor_po ?? '-' }}</a><br>
                                <b>Tanggal:</b> {{ $receiving->tanggal_terima->format('d/m/Y') }}
                            </div>
                        </div>
                    </div>

                    @if($receiving->catatan)
                    <div class="alert alert-light border mt-3">
                        <i class="fas fa-sticky-note text-warning mr-2"></i> <strong>Catatan:</strong> {{ $receiving->catatan }}
                    </div>
                    @endif

                    {{-- Tabel Item --}}
                    <div class="table-responsive mt-4">
                        <table class="table table-striped table-bordered">
                            <thead class="bg-secondary">
                                <tr>
                                    <th style="width: 5%">No</th>
                                    <th>Kode Part</th>
                                    <th>Nama Barang</th>
                                    <th class="text-center" style="width: 15%">Qty Terima</th>
                                    <th class="text-center" style="width: 15%">Lolos QC</th>
                                    <th class="text-center" style="width: 15%">Reject</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($receiving->details as $index => $detail)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>{{ $detail->barang->part_code ?? '-' }}</td>
                                        <td>{{ $detail->barang->part_name ?? 'Barang dihapus' }}</td>
                                        <td class="text-center font-weight-bold">{{ $detail->qty_terima }}</td>
                                        <td class="text-center text-success">
                                            {{ $detail->qty_lolos_qc }}
                                        </td>
                                        <td class="text-center text-danger">
                                            {{ $detail->qty_gagal_qc }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center">Data detail tidak tersedia.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Riwayat Putaway --}}
            <div class="card card-outline card-info shadow-sm collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-2"></i> Log Pergerakan Stok (Putaway)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="pl-3">Barang</th>
                                <th>Rak</th>
                                <th>Qty</th>
                                <th>User</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockMovements as $movement)
                                <tr>
                                    <td class="pl-3">{{ $movement->barang->part_name ?? '-' }}</td>
                                    <td><span class="badge badge-secondary">{{ $movement->rak->kode_rak ?? 'Gudang Utama' }}</span></td>
                                    <td class="font-weight-bold text-success">+{{ abs($movement->jumlah) }}</td>
                                    <td>{{ $movement->user->nama ?? '-' }}</td>
                                    <td>{{ $movement->created_at->format('d/m H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Belum ada barang yang disimpan ke Rak.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Kanan: Timeline Status --}}
        <div class="col-md-4">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">Timeline Proses</h3>
                </div>
                <div class="card-body">
                    <div class="timeline timeline-inverse">
                        {{-- 1. RECEIVING --}}
                        <div>
                            <i class="fas fa-truck bg-success"></i>
                            <div class="timeline-item">
                                <span class="time"><i class="far fa-clock"></i> {{ $receiving->created_at->format('d M H:i') }}</span>
                                <h3 class="timeline-header"><a href="#">Barang Diterima</a></h3>
                                <div class="timeline-body">
                                    Oleh: {{ $receiving->receivedBy->nama ?? 'System' }}
                                </div>
                            </div>
                        </div>

                        {{-- 2. QC --}}
                        @if($receiving->qc_at || $receiving->status == 'PENDING_QC')
                        <div>
                            <i class="fas fa-clipboard-check {{ $receiving->qc_at ? 'bg-primary' : 'bg-secondary' }}"></i>
                            <div class="timeline-item">
                                @if($receiving->qc_at)
                                    <span class="time"><i class="far fa-clock"></i> {{ $receiving->qc_at->format('d M H:i') }}</span>
                                    <h3 class="timeline-header"><a href="#">Quality Control</a></h3>
                                    <div class="timeline-body">
                                        Status: <strong>Lolos QC</strong><br>
                                        Oleh: {{ $receiving->qcBy->nama ?? '-' }}
                                    </div>
                                @else
                                    <h3 class="timeline-header text-muted">Menunggu QC...</h3>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- 3. PUTAWAY --}}
                        <div>
                            <i class="fas fa-box-open {{ $receiving->putaway_at ? 'bg-info' : 'bg-gray' }}"></i>
                            <div class="timeline-item">
                                @if($receiving->putaway_at)
                                    <span class="time"><i class="far fa-clock"></i> {{ $receiving->putaway_at->format('d M H:i') }}</span>
                                    <h3 class="timeline-header"><a href="#">Putaway (Disimpan)</a></h3>
                                    <div class="timeline-body">
                                        Barang telah masuk stok rak.<br>
                                        Oleh: {{ $receiving->putawayBy->nama ?? '-' }}
                                    </div>
                                @else
                                    <h3 class="timeline-header text-muted">Menunggu Putaway...</h3>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <i class="far fa-clock bg-gray"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Tombol Aksi Cepat jika status masih gantung --}}
            @if($receiving->status == 'PENDING_QC' && auth()->user()->can('perform-qc'))
                <a href="{{ route('admin.qc.index') }}" class="btn btn-warning btn-block mb-3">
                    <i class="fas fa-clipboard-check mr-1"></i> Proses QC Sekarang
                </a>
            @endif
            @if($receiving->status == 'PENDING_PUTAWAY' && auth()->user()->can('perform-warehouse-ops'))
                <a href="{{ route('admin.putaway.index') }}" class="btn btn-info btn-block mb-3">
                    <i class="fas fa-box mr-1"></i> Proses Putaway Sekarang
                </a>
            @endif

        </div>
    </div>
@stop