<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;

class ServiceImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $skippedDealerCount = 0; // ++ Counter baru untuk dealer yang tidak cocok

    // ++ Variabel untuk menyimpan kode dealer user
    private $userDealerCode;
    private $lokasiMapping = [];

    // ++ Constructor untuk menerima kode dealer dari controller
    public function __construct(string $userDealerCode)
    {
        $this->userDealerCode = $userDealerCode;
        // Pre-load lokasi mapping untuk efisiensi
        $this->lokasiMapping = Lokasi::pluck('id', 'kode_gudang')->toArray();
    }

    public function model(array $row)
    {
        // Normalisasi dan validasi input
        $invoiceNo = trim($row['invoice_no']);
        $dealerCode = trim($row['dealer']);

        // ++ VALIDASI 1: Cek apakah dealer code di file cocok dengan dealer code user
        if ($dealerCode !== $this->userDealerCode) {
            $this->skippedDealerCount++;
            return null; // Lewati baris ini
        }

        // VALIDASI 2: Cek duplikat berdasarkan invoice_no
        $existingService = Service::where('invoice_no', $invoiceNo)->first();
        if ($existingService) {
            $this->skippedCount++;
            return null; // Lewati baris ini jika sudah ada
        }

        // VALIDASI 3: Pastikan dealer code ada di tabel lokasi
        if (!isset($this->lokasiMapping[$dealerCode])) {
            Log::warning("Skipping invoice {$invoiceNo}: Dealer code '{$dealerCode}' not found in 'lokasi' table.");
            $this->skippedCount++;
            return null;
        }

        DB::transaction(function () use ($row, $invoiceNo, $dealerCode) {
            try {
                // Konversi tanggal Excel
                $regDate = is_numeric($row['reg_date']) ? Date::excelToDateTimeObject($row['reg_date'])->format('Y-m-d') : null;

                $service = Service::create([
                    'invoice_no' => $invoiceNo,
                    'reg_date' => $regDate,
                    'dealer_code' => $dealerCode,
                    'lokasi_id' => $this->lokasiMapping[$dealerCode] ?? null, // Tambahkan lokasi_id
                    'customer_name' => $row['customer_name'],
                    'plate_no' => $row['plate_no'],
                    'total_labor' => $row['total_labor'] ?? 0,
                    'total_part_service' => $row['total_part_service'] ?? 0,
                    'total_oil_service' => $row['total_oil_service'] ?? 0,
                    'total_retail_parts' => $row['total_retail_parts'] ?? 0,
                    'total_retail_oil' => $row['total_retail_oil'] ?? 0,
                    'benefit_amount' => $row['benefit_amount'] ?? 0,
                    'total_amount' => $row['total_amount'] ?? 0,
                    'e_payment_amount' => $row['e_payment_amount'] ?? 0,
                    'cash_amount' => $row['cash_amount'] ?? 0,
                    'debit_amount' => $row['debit_amount'] ?? 0,
                    'total_payment' => $row['total_payment'] ?? 0,
                    'balance' => $row['balance'] ?? 0,
                ]);

                // Proses detail jika ada
                if (!empty($row['item_code'])) {
                    $service->details()->create([
                        'item_category' => $row['item_category'],
                        'item_code' => $row['item_code'],
                        'item_name' => $row['item_name'],
                        'quantity' => $row['quantity'],
                        'price' => $row['price'],
                    ]);
                }

                $this->importedCount++;
            } catch (\Exception $e) {
                // Log error jika terjadi kegagalan dalam transaksi
                Log::error("Failed to import service invoice {$invoiceNo}. Error: " . $e->getMessage());
                $this->skippedCount++;
            }
        });

        // Karena ToModel harus return model, kita return null di sini karena
        // pembuatan model sudah ditangani dalam DB::transaction
        return null;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    // ++ Fungsi baru untuk mendapatkan jumlah data yang dilewati karena salah dealer
    public function getSkippedDealerCount(): int
    {
        return $this->skippedDealerCount;
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function chunkSize(): int
    {
        return 200;
    }
}