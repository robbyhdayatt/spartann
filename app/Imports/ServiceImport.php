<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
    private $userId;
    private $lokasiMapping = [];
    private $convertMapping = [];

    public function __construct(string $userDealerCode)
    {
        $this->userDealerCode = $userDealerCode;
        $this->userId = Auth::id();
        $this->lokasiMapping = Lokasi::pluck('id', 'kode_lokasi')->toArray();
        $this->convertMapping = DB::table('converts')
             ->get()
             ->keyBy(function ($item) {
                 return $this->normalizeString($item->nama_job);
             })
             ->toArray();
    }

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
            try {
               return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', trim(str_replace('"', '', $dateValue)))->format('Y-m-d');
        } catch (\Exception $e) { return null; }
    }

    // ++ PERBAIKAN: Logic pembersih angka lebih pintar ++
    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return floatval($value);
        if (empty($value)) return 0;

        $str = strval($value);
        $str = preg_replace('/[Rp\s]/i', '', $str); // Hapus Rp dan spasi
        
        // Handle suffix .0 atau .00 (format desimal Excel string)
        if (preg_match('/\.0+$/', $str)) {
            $str = preg_replace('/\.0+$/', '', $str);
        }

        $isNegative = (strpos($str, '-') !== false);
        $str = preg_replace('/[^0-9.,]/', '', $str); // Sisakan angka, titik, koma

        // Deteksi Ribuan vs Desimal
        if (strpos($str, '.') !== false && strpos($str, ',') !== false) {
            $str = str_replace('.', '', $str); // 25.000,00 -> 25000,00
            $str = str_replace(',', '.', $str); // -> 25000.00
        } elseif (strpos($str, '.') !== false) {
            // Asumsi 25.000 adalah 25 ribu (Format Indo)
            // Kecuali jika angka kecil misal 1.5 (jarang di harga)
            $str = str_replace('.', '', $str); 
        } elseif (strpos($str, ',') !== false) {
             $str = str_replace(',', '.', $str);
        }

        $val = floatval($str);
        return $isNegative ? -$val : $val;
    }

    private function processStockDeduction($barangId, $qty, $serviceId, $lokasiId, $invoiceNo)
    {
        if (!$barangId || !$lokasiId || $qty <= 0) return 0;

        $barangMaster = Barang::find($barangId);
        $namaBarang = $barangMaster->part_name ?? 'Unknown Item';

        $stokTersedia = InventoryBatch::where('barang_id', $barangId)
            ->where('lokasi_id', $lokasiId)
            ->sum('quantity');

        if ($stokTersedia < $qty) {
            throw new \Exception("Stok Barang '{$namaBarang}' (ID: {$barangId}) TIDAK MENCUKUPI. Butuh: {$qty}, Tersedia: {$stokTersedia}. Import Invoice #{$invoiceNo} dibatalkan.");
        }

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
            $costPerUnit = $barangMaster->selling_out; 
            $totalCost += ($costPerUnit * $potong);
            $batch->decrement('quantity', $potong);

            StockMovement::create([
                'barang_id'      => $barangId,
                'lokasi_id'      => $lokasiId,
                'rak_id'         => $batch->rak_id,
                'jumlah'         => -$potong,
                'stok_sebelum'   => $batch->quantity + $potong,
                'stok_sesudah'   => $batch->quantity,
                'referensi_type' => 'App\Models\Service',
                'referensi_id'   => $serviceId,
                'keterangan'     => "Pemakaian Service #{$invoiceNo} ({$namaBarang})",
                'user_id'        => $this->userId,
            ]);
            $sisaQty -= $potong;
        }

        return ($qty > 0) ? ($totalCost / $qty) : 0;
    }

    // ++ DETEKSI PERGESERAN KOLOM (Shift) ++
    private function getColumnIndex($baseIndex, $row, $checkPartColumn = true) 
    {
        $offset = 0;
        // Cek Indikator Pergeseran:
        // Jika kolom 23 (Parts No normal) kosong TAPI kolom 25 (Parts No geser) ada isinya
        // Maka asumsikan terjadi pergeseran 2 kolom ke kanan untuk data Parts & Amount
        if ($checkPartColumn && empty($row[23]) && !empty($row[25])) {
            $offset = 2;
        }
        
        // Jika index yang diminta berada di area yang terpengaruh shift (> 22)
        if ($baseIndex > 22) {
            return $baseIndex + $offset;
        }
        return $baseIndex;
    }

    private function createServiceDetail(Service $service, array $row, int $rowIndex)
    {
        $hasActivity = false;

        // Tentukan Offset Shift (Cek kolom 23 vs 25)
        $offset = (empty($row[23]) && !empty($row[25])) ? 2 : 0;

        // Mapping Index (Dinamis dengan Offset)
        $serviceCategoryCode_idx = 18; 
        $servicePackageName_idx = 21; 
        $laborCostService_idx = 22; 
        
        // Kolom Parts ke belakang kena shift
        $partsNo_idx = 23 + $offset;      
        $partsName_idx = 24 + $offset;     
        $partsQty_idx = 25 + $offset;      
        $partsPrice_idx = 26 + $offset;    

        $serviceCategoryCode = $this->currentServiceCategoryCode;
        if (empty($serviceCategoryCode)) {
            $serviceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
        }

        $servicePackageNameRaw = $row[$servicePackageName_idx] ?? null;
        $servicePackageNameNormalized = $this->normalizeString($servicePackageNameRaw);

        $laborCostServiceValue = $row[$laborCostService_idx] ?? null;
        $cleanedLaborCostService = $this->cleanNumeric($laborCostServiceValue);
        
        $partsNo = trim($row[$partsNo_idx] ?? null);
        $partsName = trim($row[$partsName_idx] ?? null);
        $partsQty = $row[$partsQty_idx] ?? null;
        $partsPrice = $row[$partsPrice_idx] ?? null;
        
        $lokasiId = $service->lokasi_id;

        // 1. PROSES PAKET / JASA
        if (!empty($servicePackageNameNormalized)) {
            // Cek Master Convert
            if (isset($this->convertMapping[$servicePackageNameNormalized])) {
                $convertData = $this->convertMapping[$servicePackageNameNormalized];
                $barang = Barang::where('part_code', $convertData->part_code)->first();
                $costPrice = 0;

                try {
                    if ($barang) {
                        $costPrice = $this->processStockDeduction($barang->id, $convertData->quantity, $service->id, $lokasiId, $service->invoice_no);
                    }

                    // Simpan Detail PART dari Paket
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

                    // Simpan Detail JASA dari Paket (Jika ada nilai di Excel)
                    if ($cleanedLaborCostService > 0) {
                        $service->details()->create([
                            'item_category' => 'JASA',
                            'service_category_code' => $serviceCategoryCode,
                            'service_package_name' => $servicePackageNameRaw,
                            'labor_cost_service' => $cleanedLaborCostService,
                            'item_code' => null,
                            'item_name' => $servicePackageNameRaw . ' (Jasa)',
                            'quantity' => 1,
                            'price' => $cleanedLaborCostService, 
                            'barang_id' => null,
                            'cost_price' => 0,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Baris {$rowIndex}: Gagal detail konversi. " . $e->getMessage());
                    throw $e; 
                }
            }
            // Jika Bukan Konversi tapi ada Jasa
            elseif ($cleanedLaborCostService > 0) { 
                try {
                    $service->details()->create([
                        'item_category' => 'JASA',
                        'service_category_code' => $serviceCategoryCode,
                        'service_package_name' => $servicePackageNameRaw,
                        'labor_cost_service' => $cleanedLaborCostService,
                        'item_code' => null,
                        'item_name' => $servicePackageNameRaw,
                        'quantity' => 1,
                        'price' => $cleanedLaborCostService,
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

        // 2. PROSES PART MANUAL (Dari Excel)
        if (!empty($partsNo) && !empty($partsName)) {
            $cleanedPartsQty = $this->cleanNumeric($partsQty);
            $cleanedPartsPrice = $this->cleanNumeric($partsPrice);
            $itemCategory = (stripos($partsName, 'oli') !== false || stripos($partsName, 'yamalube') !== false) ? 'OLI' : 'PART';

            if ($cleanedPartsQty <= 0) $cleanedPartsQty = 1;

            $barang = Barang::where('part_code', $partsNo)->first();
            $costPrice = 0;

            try {
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
                Log::error("Baris {$rowIndex}: Gagal detail {$itemCategory}. " . $e->getMessage());
                $this->skippedCount++;
            }
        }

        if (!$hasActivity && $rowIndex > 2) {
            Log::info("Baris {$rowIndex} dilewati (tidak ada data valid).");
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();

        if ($rowIndex <= 2 || empty(array_filter($row)) || strtolower(trim($row[1] ?? '')) == 'total') return;

        // Tentukan Offset untuk Kolom Header juga (karena Header Total ada di kanan)
        $offset = (empty($row[23]) && !empty($row[25])) ? 2 : 0;

        $invoiceNo_idx = 9;
        $dealerCode_idx = 2;
        $regDate_idx = 4;
        $serviceCategoryCode_idx = 18;

        $invoiceNo = trim($row[$invoiceNo_idx] ?? null);
        $dealerCode = trim($row[$dealerCode_idx] ?? null);

        try {
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

            if (!empty($invoiceNo)) {
                $serviceExists = Service::where('invoice_no', $invoiceNo)->where('dealer_code', $dealerCode)->exists();
                if ($serviceExists) {
                    $this->skippedDuplicateCount++;
                    $this->currentService = null;
                    $this->currentServiceCategoryCode = null;
                    return;
                }

                $regDate = $this->parseDate($row[$regDate_idx]);
                if (empty($regDate)) throw new \Exception("Tanggal registrasi invalid.");

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
                    'technician_name' => $row[43 + $offset] ?? null, // Kena Shift
                    'customer_name' => $row[10] ?? null,
                    'customer_ktp' => $row[11] ?? null,
                    'customer_npwp_no' => $row[12] ?? null,
                    'customer_npwp_name' => $row[13] ?? null,
                    'customer_phone' => $row[14] ?? null,
                    'mc_brand' => $row[15] ?? null,
                    'mc_model_name' => $row[16] ?? null,
                    'mc_frame_no' => $row[17] ?? null,
                    'payment_type' => $row[27 + $offset] ?? null,
                    'transaction_code' => $row[28 + $offset] ?? null,
                    
                    // Kolom Angka dengan Shift Offset
                    'e_payment_amount' => $this->cleanNumeric($row[30 + $offset] ?? 0),
                    'cash_amount' => $this->cleanNumeric($row[31 + $offset] ?? 0),
                    'debit_amount' => $this->cleanNumeric($row[32 + $offset] ?? 0),
                    'total_down_payment' => $this->cleanNumeric($row[33 + $offset] ?? 0),
                    'total_labor' => $this->cleanNumeric($row[34 + $offset] ?? 0),
                    'total_part_service' => $this->cleanNumeric($row[35 + $offset] ?? 0),
                    'total_oil_service' => $this->cleanNumeric($row[36 + $offset] ?? 0),
                    'total_retail_parts' => $this->cleanNumeric($row[37 + $offset] ?? 0),
                    'total_retail_oil' => $this->cleanNumeric($row[38 + $offset] ?? 0),
                    'total_amount' => $this->cleanNumeric($row[39 + $offset] ?? 0),
                    'benefit_amount' => $this->cleanNumeric($row[40 + $offset] ?? 0),
                    'total_payment' => $this->cleanNumeric($row[41 + $offset] ?? 0),
                    'balance' => $this->cleanNumeric($row[42 + $offset] ?? 0),
                ]);
                $this->importedCount++;
                $this->currentServiceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
            } elseif (empty($invoiceNo) && !$this->currentService) {
                $this->skippedCount++;
                return;
            }

            if ($this->currentService) {
                $this->createServiceDetail($this->currentService, $row, $rowIndex);
            }

        } catch (\Exception $e) {
            Log::error("Baris {$rowIndex} Error: " . $e->getMessage());
            $this->skippedCount++;
            if (!empty($invoiceNo)) $this->currentService = null;
        }
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getSkippedDealerCount(): int { return $this->skippedDealerCount; }
    public function getSkippedDuplicateCount(): int { return $this->skippedDuplicateCount; }
    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}