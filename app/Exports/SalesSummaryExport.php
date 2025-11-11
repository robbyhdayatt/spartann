<?php

namespace App\Exports;

use App\Models\PenjualanDetail;
use App\Models\Penjualan;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesSummaryExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithStyles,
    WithColumnFormatting,
    WithEvents
{
    protected $startDate;
    protected $endDate;
    protected $dealerId;
    protected $grandTotals; // Kita simpan grand total di sini

    public function __construct($startDate, $endDate, $dealerId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dealerId = $dealerId;

        // Inisialisasi grand total
        $this->grandTotals = [
            'qty' => 0,
            'penjualan' => 0,
            'modal' => 0,
            'keuntungan' => 0,
        ];
    }

    public function query()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isRestrictedUser = !$user->hasRole(['SA', 'PIC', 'MA']) && $user->lokasi_id;

        // Tentukan lokasi_id yang akan difilter
        $selectedLokasiId = $this->dealerId;
        if ($isRestrictedUser) {
            $selectedLokasiId = $user->lokasi_id; // Paksa filter ke lokasi user
        }

        // 1. Query Eloquent untuk data rinci (sama seperti di controller)
        $query = PenjualanDetail::with([
            'penjualan' => function ($query) {
                $query->with(['lokasi', 'konsumen', 'sales']);
            },
            'barang'
        ])
        ->whereHas('penjualan', function ($q) use ($selectedLokasiId) {
            // Filter tanggal
            $q->whereBetween('tanggal_jual', [$this->startDate, $this->endDate]);

            // Terapkan filter dealer/lokasi
            if ($selectedLokasiId) {
                $q->where('lokasi_id', $selectedLokasiId);
            }
        })
        ->orderBy(
            Penjualan::select('tanggal_jual')
                ->whereColumn('id', 'penjualan_details.penjualan_id')
                ->limit(1),
            'desc'
        )
        ->orderBy(
            Penjualan::select('nomor_faktur')
                ->whereColumn('id', 'penjualan_details.penjualan_id')
                ->limit(1),
            'desc'
        );

        // 2. Hitung Grand Total secara manual
        // Kita clone query agar bisa get() untuk kalkulasi, lalu return query asli
        $dataForTotals = (clone $query)->get();

        foreach ($dataForTotals as $data) {
            $modal_satuan = $data->barang->harga_modal ?? 0;
            $total_modal_item = $data->qty_jual * $modal_satuan;
            $total_keuntungan_item = $data->subtotal - $total_modal_item;

            $this->grandTotals['qty'] += $data->qty_jual;
            $this->grandTotals['penjualan'] += $data->subtotal;
            $this->grandTotals['modal'] += $total_modal_item;
            $this->grandTotals['keuntungan'] += $total_keuntungan_item;
        }

        // 3. Kembalikan query asli untuk diproses oleh FromQuery
        return $query;
    }

    public function headings(): array
    {
        // Header baru sesuai dengan 11 kolom di view
        return [
            'Tanggal Jual',
            'No. Faktur',
            'Dealer (Lokasi)',
            'Konsumen',
            'Sales',
            'Kode Barang',
            'Nama Barang',
            'Qty',
            'Total Penjualan',
            'Total Modal (HPP)',
            'Total Keuntungan',
        ];
    }

    public function map($row): array
    {
        // $row adalah instance PenjualanDetail
        $modal_satuan = $row->barang->harga_modal ?? 0;
        $total_modal = $row->qty_jual * $modal_satuan;
        $total_keuntungan = $row->subtotal - $total_modal;

        $tanggalJual = $row->penjualan->tanggal_jual;
        if ($tanggalJual instanceof \Carbon\Carbon) {
            $tanggalJual = $tanggalJual->format('d-m-Y');
        }

        return [
            $tanggalJual,
            $row->penjualan->nomor_faktur ?? '-',
            $row->penjualan->lokasi->nama_lokasi ?? '-',
            $row->penjualan->konsumen->nama_konsumen ?? '-',
            $row->penjualan->sales->nama ?? '-',
            "\t" . ($row->barang->part_code ?? 'N/A'), // "\t" untuk format teks di Excel
            $row->barang->part_name ?? 'N/A',
            $row->qty_jual,
            $row->subtotal,
            $total_modal,
            $total_keuntungan,
        ];
    }

    public function title(): string
    {
        return 'Laporan Penjualan Rinci';
    }

    public function columnFormats(): array
    {
        // Sesuaikan kolom. H=Qty, I=Penjualan, J=Modal, K=Keuntungan
        return [
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Format Qty
            'I' => '"Rp " #,##0_,-', // Format Rupiah
            'J' => '"Rp " #,##0_,-', // Format Rupiah
            'K' => '"Rp " #,##0_,-', // Format Rupiah
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Baris 4 adalah baris header kita (setelah 3 baris judul)
        $headerRow = 4;

        // Ubah range header menjadi A sampai K
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9D9D9'], // Abu-abu muda
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Sisipkan 3 baris baru di paling atas
                $sheet->insertNewRowBefore(1, 3);

                // 2. Tambahkan Judul (Range A-K)
                $sheet->setCellValue('A1', 'Laporan Penjualan Rinci');
                $sheet->mergeCells('A1:K1'); // Merge sampai K
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // 3. Tambahkan Periode Tanggal (Range A-K)
                $tanggalMulai = $this->startDate instanceof \Carbon\Carbon ? $this->startDate->format('d-m-Y') : \Carbon\Carbon::parse($this->startDate)->format('d-m-Y');
                $tanggalSelesai = $this->endDate instanceof \Carbon\Carbon ? $this->endDate->format('d-m-Y') : \Carbon\Carbon::parse($this->endDate)->format('d-m-Y');

                $sheet->setCellValue('A2', 'Periode: ' . $tanggalMulai . ' s/d ' . $tanggalSelesai);
                $sheet->mergeCells('A2:K2'); // Merge sampai K
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 4. Atur lebar kolom (A-K)
                foreach (range('A', 'K') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. Tambahkan Footer Grand Total
                $lastRow = $sheet->getHighestRow();
                $footerRow = $lastRow + 1;

                // Merge kolom A-G untuk teks "GRAND TOTAL"
                $sheet->mergeCells("A{$footerRow}:G{$footerRow}");
                $sheet->setCellValue("A{$footerRow}", 'GRAND TOTAL');
                $sheet->getStyle("A{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Tulis data Grand Total di kolom H, I, J, K
                $sheet->setCellValue("H{$footerRow}", $this->grandTotals['qty']);
                $sheet->setCellValue("I{$footerRow}", $this->grandTotals['penjualan']);
                $sheet->setCellValue("J{$footerRow}", $this->grandTotals['modal']);
                $sheet->setCellValue("K{$footerRow}", $this->grandTotals['keuntungan']);

                // Terapkan style bold ke baris footer
                $sheet->getStyle("A{$footerRow}:K{$footerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // Format angka di footer (H, I, J, K)
                $sheet->getStyle("H{$footerRow}")
                      ->getNumberFormat()
                      ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                $sheet->getStyle("I{$footerRow}:K{$footerRow}")
                      ->getNumberFormat()
                      ->setFormatCode('"Rp " #,##0_,-');
            },
        ];
    }
}
