<?php

namespace App\Exports;

use App\Models\ReceivingDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseJournalExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Gunakan relasi 'barang'
        return ReceivingDetail::with(['receiving.purchaseOrder.supplier', 'barang'])
            ->whereHas('receiving', function ($query) {
                $query->whereBetween('tanggal_terima', [$this->startDate, $this->endDate]);
            })
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Terima',
            'No Penerimaan',
            'No PO',
            'Supplier',
            'Kode Barang',
            'Nama Barang',
            'Qty Diterima',
        ];
    }

    public function map($detail): array
    {
        return [
            $detail->receiving->tanggal_terima,
            $detail->receiving->nomor_penerimaan,
            $detail->receiving->purchaseOrder->nomor_po ?? '-',
            $detail->receiving->purchaseOrder->supplier->nama_supplier ?? 'Internal',
            $detail->barang->part_code ?? '-',
            $detail->barang->part_name ?? '-',
            $detail->qty_terima,
        ];
    }
}
