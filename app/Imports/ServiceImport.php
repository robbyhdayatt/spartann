<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch; // Tambahan
use App\Models\StockMovement;  // Tambahan
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Tambahan
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class ServiceImport implements OnEachRow, WithChunkReading
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $skippedDealerCount = 0;
    private $skippedDuplicateCount = 0;

    private $currentService = null;
    private $currentServiceCategoryCode = null;

    private $userDealerCode;
    private $userId; // Simpan ID user yang melakukan import
    private $lokasiMapping = [];
    private $convertMapping = [];

    public function __construct(string $userDealerCode)
    {
        $this->userDealerCode = $userDealerCode;
        $this->userId = Auth::id(); // Ambil ID user saat init
        $this->lokasiMapping = Lokasi::pluck('id', 'kode_lokasi')->toArray();

        // Ambil data convert (cache)
        // Pastikan nama tabel/view benar ('converts' atau 'converts_main')
        $this->convertMapping = DB::table('converts')
             ->get()
             ->keyBy(function ($item) {
                 return $this->normalizeString($item->nama_job);
             })
             ->toArray();
    }

    // ... (Function normalizeString, parseDate, cleanNumeric TETAP SAMA, tidak perlu diubah) ...
    private function normalizeString($value)
    {
        if (!is_string($value)) return $value;
        $value = str_replace(['–', '—', '−', '‒', '―'], '-', $value);
        $value = preg_replace('/[\h\s\p{Zs}]+/u', ' ', $value);
        $value = preg_replace('/[^\P{Cc}\t\n\r]/u', '', $value);
        return trim($value);
    }

    private function parseDate($dateValue)
    {
        if (is_numeric($dateValue)) {
            if ($dateValue > 2958465) {
                 // Logic timestamp ms/sec
                 try {
                    return ($dateValue > 1000000000000)
                        ? \Carbon\Carbon::createFromTimestampMs($dateValue)->format('Y-m-d')
                        : \Carbon\Carbon::createFromTimestamp($dateValue)->format('Y-m-d');
                 } catch (\Exception $e) { /* fallback */ }
            }
            try {
                return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
        try {
            $dateValue = trim(str_replace('"', '', $dateValue));
            return \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::createFromFormat('m/d/Y', $dateValue)->format('Y-m-d');
            } catch (\Exception $e2) { return null; }
        }
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return floatval($value);
        $cleaned = preg_replace('/[Rp. ]/', '', strval($value));
        $cleaned = str_replace(',', '.', $cleaned);
        $cleaned = preg_replace('/[^0-9.-]/', '', $cleaned);
        return is_numeric($cleaned) ? floatval($cleaned) : 0;
    }

    private function processStockDeduction($barangId, $qty, $serviceId, $lokasiId, $invoiceNo)
    {
        if (!$barangId || !$lokasiId || $qty <= 0) return 0;

        $barangMaster = Barang::find($barangId);
        $namaBarang = $barangMaster->part_name ?? 'Unknown Item';

        // 1. Cek Stok Tersedia
        $stokTersedia = InventoryBatch::where('barang_id', $barangId)
            ->where('lokasi_id', $lokasiId)
            ->sum('quantity');

        // ++ PERUBAHAN: STOP JIKA STOK HABIS ++
        if ($stokTersedia < $qty) {
            // Throw Exception agar ditangkap oleh try-catch di createServiceDetail
            // Ini akan menyebabkan baris ini di-skip dan masuk ke daftar error
            throw new \Exception("Stok Barang '{$namaBarang}' (ID: {$barangId}) TIDAK MENCUKUPI. Butuh: {$qty}, Tersedia: {$stokTersedia}. Import dibatalkan.");
        }

        // 2. Proses Potong Stok (FIFO)
        $batches = InventoryBatch::where('barang_id', $barangId)
            ->where('lokasi_id', $lokasiId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $sisaQty = $qty;
        $totalCost = 0;

        foreach ($batches as $batch) {
            if ($sisaQty <= 0) break;

            $potong = min($batch->quantity, $sisaQty);

            // Gunakan Selling Out sebagai Cost
            $costPerUnit = $barangMaster->selling_out;
            $totalCost += ($costPerUnit * $potong);

            $batch->decrement('quantity', $potong);

            // ++ PERUBAHAN: KETERANGAN LEBIH JELAS ++
            StockMovement::create([
                'barang_id'      => $barangId,
                'lokasi_id'      => $lokasiId,
                'rak_id'         => $batch->rak_id,
                'jumlah'         => -$potong,
                'stok_sebelum'   => $batch->quantity + $potong,
                'stok_sesudah'   => $batch->quantity,
                'referensi_type' => 'App\Models\Service',
                'referensi_id'   => $serviceId,
                'keterangan'     => "Import Service #{$invoiceNo} ({$namaBarang})", // Keterangan Jelas
                'user_id'        => $this->userId,
            ]);

            $sisaQty -= $potong;
        }

        return ($qty > 0) ? ($totalCost / $qty) : 0;
    }

    private function createServiceDetail(Service $service, array $row, int $rowIndex)
    {
        $hasActivity = false;

        // Definisi Index Kolom (Sama)
        $serviceCategoryCode_idx = 18;
        $servicePackageName_idx = 21;
        $laborCostService_idx = 22;
        $partsNo_idx = 23;
        $partsName_idx = 24;
        $partsQty_idx = 25;
        $partsPrice_idx = 26;

        $serviceCategoryCode = $this->currentServiceCategoryCode;
        if (empty($serviceCategoryCode)) {
            $serviceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
        }

        $servicePackageNameRaw = $row[$servicePackageName_idx] ?? null;
        $servicePackageNameNormalized = $this->normalizeString($servicePackageNameRaw);
        $laborCostServiceValue = $row[$laborCostService_idx] ?? null;
        $cleanedLaborCostService = $this->cleanNumeric($laborCostServiceValue);
        $laborCost = $laborCostServiceValue;
        $cleanedLaborCost = $cleanedLaborCostService;

        $partsNo = trim($row[$partsNo_idx] ?? null);
        $partsName = trim($row[$partsName_idx] ?? null);
        $partsQty = $row[$partsQty_idx] ?? null;
        $partsPrice = $row[$partsPrice_idx] ?? null;

        $lokasiId = $service->lokasi_id;

        if (!empty($servicePackageNameNormalized)) {
            // 1. Cek KONVERSI
            if (isset($this->convertMapping[$servicePackageNameNormalized])) {
                $convertData = $this->convertMapping[$servicePackageNameNormalized];

                $barang = Barang::where('part_code', $convertData->part_code)->first();
                $costPrice = 0;

                try {
                    // ++ UPDATE: Kirim Invoice No ++
                    if ($barang) {
                        $costPrice = $this->processStockDeduction($barang->id, $convertData->quantity, $service->id, $lokasiId, $service->invoice_no);
                    }

                    $service->details()->create([
                        'item_category' => 'PART',
                        'service_category_code' => $serviceCategoryCode,
                        'service_package_name' => null,
                        'labor_cost_service' => 0,
                        'item_code' => $convertData->part_code,
                        'item_name' => $convertData->part_name,
                        'quantity' => $convertData->quantity,
                        'price' => $convertData->retail,
                        'barang_id' => $barang ? $barang->id : null,
                        'cost_price' => $costPrice,
                    ]);
                    $hasActivity = true;
                } catch (\Exception $e) {
                    Log::error("Baris {$rowIndex}: Gagal detail konversi. " . $e->getMessage());
                    $this->skippedCount++;
                }
            }
            // 2. JASA BIASA
            elseif ($laborCost !== null) {
                try {
                    $service->details()->create([
                        'item_category' => 'JASA',
                        'service_category_code' => $serviceCategoryCode,
                        'service_package_name' => $servicePackageNameRaw,
                        'labor_cost_service' => $cleanedLaborCostService,
                        'item_code' => null,
                        'item_name' => null,
                        'quantity' => 1,
                        'price' => $cleanedLaborCost,
                        'barang_id' => null,
                        'cost_price' => 0,
                    ]);
                    $hasActivity = true;
                } catch (\Exception $e) {
                    Log::error("Baris {$rowIndex}: Gagal detail JASA. " . $e->getMessage());
                    $this->skippedCount++;
                }
            }
        }

        // --- Logika untuk PART atau OLI Manual ---
        if (!empty($partsNo) && !empty($partsName)) {
            $cleanedPartsQty = $this->cleanNumeric($partsQty);
            $cleanedPartsPrice = $this->cleanNumeric($partsPrice);
            $itemCategory = (stripos($partsName, 'oli') !== false || stripos($partsName, 'yamalube') !== false) ? 'OLI' : 'PART';

            if ($cleanedPartsQty <= 0) $cleanedPartsQty = 1;

            $barang = Barang::where('part_code', $partsNo)->first();
            $costPrice = 0;

            try {
                // ++ UPDATE: Kirim Invoice No ++
                if ($barang) {
                    $costPrice = $this->processStockDeduction($barang->id, $cleanedPartsQty, $service->id, $lokasiId, $service->invoice_no);
                }

                $service->details()->create([
                    'item_category' => $itemCategory,
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => null,
                    'labor_cost_service' => 0,
                    'item_code' => $partsNo,
                    'item_name' => $partsName,
                    'quantity' => $cleanedPartsQty,
                    'price' => $cleanedPartsPrice,
                    'barang_id' => $barang ? $barang->id : null,
                    'cost_price' => $costPrice,
                ]);
                $hasActivity = true;
            } catch (\Exception $e) {
                // Error dari processStockDeduction akan tertangkap disini
                Log::error("Baris {$rowIndex}: Gagal detail {$itemCategory}. " . $e->getMessage());
                $this->skippedCount++;
            }
        }

        if (!$hasActivity && $rowIndex > 2) {
            Log::info("Baris {$rowIndex} dilewati (tidak ada data valid).");
        }
    }

    // ... (Method onRow, getImportedCount, dll TETAP SAMA, tidak perlu diubah) ...
    public function onRow(Row $row)
    {
         // Copy paste method onRow dari kode sebelumnya di sini
         // Pastikan tidak ada perubahan logika selain pemanggilan createServiceDetail
         $rowIndex = $row->getIndex();
         $row = $row->toArray();

         // 1. Skip header/empty/total
         if ($rowIndex <= 2 || empty(array_filter($row)) || strtolower(trim($row[1] ?? '')) == 'total') {
             return;
         }

         $invoiceNo_idx = 9;
         $dealerCode_idx = 2;
         $regDate_idx = 4;
         $serviceCategoryCode_idx = 18;

         $invoiceNo = trim($row[$invoiceNo_idx] ?? null);
         $dealerCode = trim($row[$dealerCode_idx] ?? null);

         try {
             // 2. Validasi Dealer
             if (!empty($dealerCode)) {
                 if ($dealerCode !== $this->userDealerCode) {
                     $this->skippedDealerCount++;
                     $this->currentService = null;
                     return;
                 }
                 if (!isset($this->lokasiMapping[$dealerCode])) {
                     $this->skippedCount++;
                     $this->currentService = null;
                     return;
                 }
             }

             // 3. Header Row (Invoice Baru)
             if (!empty($invoiceNo)) {
                 // Cek Duplikat
                 $serviceExists = Service::where('invoice_no', $invoiceNo)
                                         ->where('dealer_code', $dealerCode)
                                         ->exists();

                 if ($serviceExists) {
                     $this->skippedDuplicateCount++;
                     $this->currentService = null;
                     $this->currentServiceCategoryCode = null;
                     return;
                 }

                 $regDate = $this->parseDate($row[$regDate_idx]);
                 if (empty($regDate)) throw new \Exception("Tanggal registrasi invalid.");

                 // Create Header
                 $this->currentService = Service::create([
                     'invoice_no' => $invoiceNo,
                     'reg_date' => $regDate,
                     'dealer_code' => $dealerCode,
                     'lokasi_id' => $this->lokasiMapping[$dealerCode] ?? null,
                     'yss' => $row[1] ?? null,
                     'point' => $row[3] ?? null,
                     'service_order' => $row[5] ?? null,
                     'plate_no' => $row[6] ?? null,
                     'work_order_no' => $row[7] ?? null,
                     'work_order_status' => $row[8] ?? null,
                     'technician_name' => $row[43] ?? null,
                     'customer_name' => $row[10] ?? null,
                     'customer_ktp' => $row[11] ?? null,
                     'customer_npwp_no' => $row[12] ?? null,
                     'customer_npwp_name' => $row[13] ?? null,
                     'customer_phone' => $row[14] ?? null,
                     'mc_brand' => $row[15] ?? null,
                     'mc_model_name' => $row[16] ?? null,
                     'mc_frame_no' => $row[17] ?? null,
                     'payment_type' => $row[27] ?? null,
                     'transaction_code' => $row[28] ?? null,
                     'e_payment_amount' => $this->cleanNumeric($row[30] ?? 0),
                     'cash_amount' => $this->cleanNumeric($row[31] ?? 0),
                     'debit_amount' => $this->cleanNumeric($row[32] ?? 0),
                     'total_down_payment' => $this->cleanNumeric($row[33] ?? 0),
                     'total_labor' => $this->cleanNumeric($row[34] ?? 0),
                     'total_part_service' => $this->cleanNumeric($row[35] ?? 0),
                     'total_oil_service' => $this->cleanNumeric($row[36] ?? 0),
                     'total_retail_parts' => $this->cleanNumeric($row[37] ?? 0),
                     'total_retail_oil' => $this->cleanNumeric($row[38] ?? 0),
                     'total_amount' => $this->cleanNumeric($row[39] ?? 0),
                     'benefit_amount' => $this->cleanNumeric($row[40] ?? 0),
                     'total_payment' => $this->cleanNumeric($row[41] ?? 0),
                     'balance' => $this->cleanNumeric($row[42] ?? 0),
                 ]);
                 $this->importedCount++;
                 $this->currentServiceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
             }
             elseif (empty($invoiceNo) && !$this->currentService) {
                 $this->skippedCount++;
                 return;
             }

             // 5. Proses Detail
             if ($this->currentService) {
                 $this->createServiceDetail($this->currentService, $row, $rowIndex);
             }

         } catch (\Exception $e) {
             Log::error("Baris {$rowIndex} Error: " . $e->getMessage());
             $this->skippedCount++;
             if (!empty($invoiceNo)) {
                 $this->currentService = null;
             }
         }
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getSkippedDealerCount(): int { return $this->skippedDealerCount; }
    public function getSkippedDuplicateCount(): int { return $this->skippedDuplicateCount; }

    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}
