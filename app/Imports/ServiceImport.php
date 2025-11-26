<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use App\Models\Barang;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Str;

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

    // Mapping Dinamis Index Kolom
    private $headerRowDetected = false;
    private $colMap = [];

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
        return trim($value);
    }

    private function parseDate($dateValue)
    {
        if (empty($dateValue)) return null;
        // Handle format Excel serial number (e.g. 45982.0)
        if (is_numeric($dateValue)) {
            try {
               return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', trim(str_replace('"', '', $dateValue)))->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::parse($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return floatval($value);
        if (empty($value)) return 0;

        $str = strval($value);
        $str = preg_replace('/[Rp\s]/i', '', $str);
        
        // PENTING: Hapus suffix .0 atau .00 (format string excel) sebelum deteksi ribuan
        // Ini mencegah 24000.0 dianggap 240.000 oleh logika ribuan di bawah
        if (preg_match('/\.0+$/', $str)) {
            $str = preg_replace('/\.0+$/', '', $str);
        }

        $isNegative = (strpos($str, '-') !== false);
        $str = preg_replace('/[^0-9.,]/', '', $str);

        // Logika Deteksi Ribuan vs Desimal
        if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
            $str = str_replace('.', '', $str); // Hapus titik ribuan
            $str = str_replace(',', '.', $str); // Ganti koma jadi titik
        } elseif (strpos($str, ',') !== false) {
             $str = str_replace(',', '.', $str);
        } elseif (strpos($str, '.') !== false) {
             // Jika ada titik tapi tidak ada koma (misal: 25.000), hapus titik
             $str = str_replace('.', '', $str);
        }

        $val = floatval($str);
        return $isNegative ? -$val : $val;
    }

    /**
     * Mendeteksi baris header dan menyimpan index kolom
     */
    private function detectHeaderRow(array $row)
    {
        // Ubah header jadi slug untuk pencarian yang konsisten
        $rowSlugs = array_map(function($item) {
            return Str::slug(trim((string)$item), '_');
        }, $row);

        // Cek apakah ini baris header (harus ada kolom kunci)
        if (!in_array('invoice_no', $rowSlugs) && !in_array('no_invoice', $rowSlugs)) {
            return false;
        }

        // Kamus Kata Kunci Header
        $possibleHeaders = [
            'invoice_no'        => ['invoice_no', 'no_invoice'],
            'dealer_code'       => ['dealer', 'dealer_code'],
            'reg_date'          => ['reg_date', 'date', 'tanggal'],
            'service_category'  => ['service_category'],
            'total_oil'         => ['total_oil_service', 'total_oli', 'total_oil'], // Handle typo/variasi
            'total_amount'      => ['total_amount', 'grand_total', 'jumlah_total'],
            'technician'        => ['technician_name'],
            
            // Kolom Detail
            'package_name'      => ['service_package'],
            'labor_cost'        => ['labor_cost_service'],
            'parts_no'          => ['parts_no'],
            'parts_name'        => ['parts_name'],
            'parts_qty'         => ['parts_qty'],
            'parts_price'       => ['parts_price'],
            
            // Nominal Lain
            'e_payment'         => ['e_payment_amount'],
            'cash'              => ['cash_amount'],
            'debit'             => ['debit_amount'],
            'dp'                => ['down_payment_dp'],
            'total_labor'       => ['total_labor'],
            'total_part'        => ['total_part_service'],
            'total_retail_parts'=> ['total_retail_parts'],
            'total_retail_oil'  => ['total_retail_oil'],
            'benefit'           => ['benefit_amount'],
            'total_payment'     => ['total_payment'],
            'balance'           => ['balance'],
            
            // Info Tambahan
            'yss'               => ['yss'],
            'point'             => ['point'],
            'service_order'     => ['service_order'],
            'plate_no'          => ['plate_no'],
            'work_order'        => ['no_work_order'],
            'wo_status'         => ['status_work_order'],
            'cust_name'         => ['customer_information', 'nama_customer', 'nama'],
            'cust_ktp'          => ['ktp'],
            'cust_phone'        => ['telepon_no'],
            'mc_brand'          => ['brand'],
            'mc_model'          => ['model_name'],
            'mc_frame'          => ['frame_no'],
            'payment_type'      => ['payment_type'],
            'trans_code'        => ['transaction_code'],
        ];

        foreach ($possibleHeaders as $key => $slugs) {
            foreach ($slugs as $slug) {
                $index = array_search($slug, $rowSlugs);
                if ($index !== false) {
                    $this->colMap[$key] = $index;
                    break;
                }
            }
        }

        // Fallback (jika header tidak terdeteksi sempurna, gunakan posisi relatif file Anda)
        if (!isset($this->colMap['cust_name'])) $this->colMap['cust_name'] = 10;
        if (!isset($this->colMap['total_amount'])) $this->colMap['total_amount'] = 39; 
        if (!isset($this->colMap['total_oil'])) $this->colMap['total_oil'] = 36;

        $this->headerRowDetected = true;
        Log::info("Header Import Detected: " . json_encode($this->colMap));
        return true;
    }

    private function getVal($row, $key, $default = null)
    {
        // Fallback Index (Sesuai urutan CSV Anda) jika mapping gagal
        $defaultIndex = [
            'dealer_code' => 2, 'invoice_no' => 9, 'reg_date' => 4,
            'service_category' => 18, 'package_name' => 21, 'labor_cost' => 22,
            'parts_no' => 23, 'parts_name' => 24, 'parts_qty' => 25, 'parts_price' => 26,
            'total_labor' => 34, 'total_part' => 35, 'total_oil' => 36, 
            'total_retail_parts' => 37, 'total_retail_oil' => 38, 'total_amount' => 39,
            'benefit' => 40, 'total_payment' => 41, 'balance' => 42, 'technician' => 43
        ];

        $index = $this->colMap[$key] ?? ($defaultIndex[$key] ?? -1);
        
        if ($index >= 0 && isset($row[$index])) {
            return $row[$index];
        }
        return $default;
    }

    private function processStockDeduction($barangId, $qty, $serviceId, $lokasiId, $invoiceNo)
    {
        if (!$barangId || !$lokasiId || $qty <= 0) return 0;

        $barangMaster = Barang::find($barangId);
        $namaBarang = $barangMaster->part_name ?? 'Unknown Item';

        $stokTersedia = InventoryBatch::where('barang_id', $barangId)
            ->where('lokasi_id', $lokasiId)
            ->sum('quantity');

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

    private function createServiceDetail(Service $service, array $row, int $rowIndex)
    {
        // Gunakan getVal() untuk konsistensi
        $serviceCategoryCode = $this->currentServiceCategoryCode;
        if (empty($serviceCategoryCode)) {
            $serviceCategoryCode = $this->getVal($row, 'service_category');
        }

        $servicePackageNameRaw = $this->getVal($row, 'package_name');
        $servicePackageNameNormalized = $this->normalizeString($servicePackageNameRaw);

        $laborCostServiceValue = $this->getVal($row, 'labor_cost');
        $cleanedLaborCostService = $this->cleanNumeric($laborCostServiceValue);
        
        $partsNo = trim($this->getVal($row, 'parts_no') ?? '');
        $partsName = trim($this->getVal($row, 'parts_name') ?? '');
        $partsQty = $this->getVal($row, 'parts_qty');
        $partsPrice = $this->getVal($row, 'parts_price');
        
        $lokasiId = $service->lokasi_id;
        $hasActivity = false;

        // 1. PROSES PAKET / JASA (Convert)
        if (!empty($servicePackageNameNormalized)) {
            if (isset($this->convertMapping[$servicePackageNameNormalized])) {
                $convertData = $this->convertMapping[$servicePackageNameNormalized];
                $barang = Barang::where('part_code', $convertData->part_code)->first();
                $costPrice = 0;

                if ($barang) {
                    try {
                        $costPrice = $this->processStockDeduction($barang->id, $convertData->quantity, $service->id, $lokasiId, $service->invoice_no);
                    } catch(\Exception $e) { Log::warning($e->getMessage()); }
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
            }
            
            // Simpan Detail JASA dari Paket
            if ($cleanedLaborCostService > 0) {
                $service->details()->create([
                    'item_category' => 'JASA',
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => $servicePackageNameRaw,
                    'labor_cost_service' => $cleanedLaborCostService,
                    'item_code' => null,
                    'item_name' => $servicePackageNameRaw . ($hasActivity ? ' (Jasa)' : ''),
                    'quantity' => 1,
                    'price' => $cleanedLaborCostService, 
                    'barang_id' => null,
                    'cost_price' => 0,
                ]);
                $hasActivity = true;
            }
        }
        // Fallback untuk Jasa non-paket
        elseif ($cleanedLaborCostService > 0) {
             $service->details()->create([
                'item_category' => 'JASA',
                'service_category_code' => $serviceCategoryCode,
                'service_package_name' => $servicePackageNameRaw,
                'labor_cost_service' => $cleanedLaborCostService,
                'item_code' => null,
                'item_name' => $servicePackageNameRaw ?? 'Biaya Jasa',
                'quantity' => 1,
                'price' => $cleanedLaborCostService, 
                'barang_id' => null,
                'cost_price' => 0,
            ]);
            $hasActivity = true;
        }

        // 2. PROSES PART MANUAL
        if (!empty($partsNo) && !empty($partsName)) {
            $cleanedPartsQty = $this->cleanNumeric($partsQty);
            $cleanedPartsPrice = $this->cleanNumeric($partsPrice);
            $itemCategory = (stripos($partsName, 'oli') !== false || stripos($partsName, 'yamalube') !== false) ? 'OLI' : 'PART';

            if ($cleanedPartsQty <= 0) $cleanedPartsQty = 1;

            $barang = Barang::where('part_code', $partsNo)->first();
            $costPrice = 0;

            if ($barang) {
                try {
                    $costPrice = $this->processStockDeduction($barang->id, $cleanedPartsQty, $service->id, $lokasiId, $service->invoice_no);
                } catch(\Exception $e) { Log::warning($e->getMessage()); }
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
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $rowArray = $row->toArray();

        if (empty(array_filter($rowArray))) return;

        // 1. Deteksi Header (Cari baris yang ada tulisan 'Invoice No')
        if (!$this->headerRowDetected) {
            if ($this->detectHeaderRow($rowArray)) {
                return; // Ini baris header, jangan diproses sebagai data
            }
        }

        if (strtolower(trim($rowArray[1] ?? '')) == 'total') return;

        // Gunakan getVal untuk mengambil data (Aman dari pergeseran)
        $invoiceNo = trim($this->getVal($rowArray, 'invoice_no') ?? '');
        $dealerCode = trim($this->getVal($rowArray, 'dealer_code') ?? '');

        try {
            // Validasi Dealer
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

            // HEADER: Buat Invoice Baru
            if (!empty($invoiceNo)) {
                $serviceExists = Service::where('invoice_no', $invoiceNo)
                                        ->where('dealer_code', $dealerCode)
                                        ->exists();
                if ($serviceExists) {
                    $this->skippedDuplicateCount++;
                    $this->currentService = null;
                    $this->currentServiceCategoryCode = null;
                    return;
                }

                $regDate = $this->parseDate($this->getVal($rowArray, 'reg_date'));
                if (empty($regDate)) throw new \Exception("Tanggal registrasi invalid.");

                // MAPPING SEMUA KOLOM ANGKA SECARA DINAMIS (TANPA OFFSET MANUAL)
                $this->currentService = Service::create([
                    'invoice_no' => $invoiceNo,
                    'reg_date' => $regDate,
                    'dealer_code' => $dealerCode,
                    'lokasi_id' => $this->lokasiMapping[$dealerCode] ?? null,
                    'yss' => $this->getVal($rowArray, 'yss'),
                    'point' => $this->getVal($rowArray, 'point'),
                    'service_order' => $this->getVal($rowArray, 'service_order'),
                    'plate_no' => $this->getVal($rowArray, 'plate_no'),
                    'work_order_no' => $this->getVal($rowArray, 'work_order'),
                    'work_order_status' => $this->getVal($rowArray, 'wo_status'),
                    'technician_name' => $this->getVal($rowArray, 'technician'),
                    'customer_name' => $this->getVal($rowArray, 'cust_name'),
                    'customer_ktp' => $this->getVal($rowArray, 'cust_ktp'),
                    'customer_phone' => $this->getVal($rowArray, 'cust_phone'),
                    'mc_brand' => $this->getVal($rowArray, 'mc_brand'),
                    'mc_model_name' => $this->getVal($rowArray, 'mc_model'),
                    'mc_frame_no' => $this->getVal($rowArray, 'mc_frame'),
                    'payment_type' => $this->getVal($rowArray, 'payment_type'),
                    'transaction_code' => $this->getVal($rowArray, 'trans_code'),
                    
                    // ANGKA DIAMBIL BERDASARKAN HEADER, BUKAN URUTAN KOLOM + OFFSET
                    'e_payment_amount' => $this->cleanNumeric($this->getVal($rowArray, 'e_payment')),
                    'cash_amount' => $this->cleanNumeric($this->getVal($rowArray, 'cash')),
                    'debit_amount' => $this->cleanNumeric($this->getVal($rowArray, 'debit')),
                    'total_down_payment' => $this->cleanNumeric($this->getVal($rowArray, 'dp')),
                    'total_labor' => $this->cleanNumeric($this->getVal($rowArray, 'total_labor')),
                    'total_part_service' => $this->cleanNumeric($this->getVal($rowArray, 'total_part')),
                    'total_oil_service' => $this->cleanNumeric($this->getVal($rowArray, 'total_oil')),
                    'total_retail_parts' => $this->cleanNumeric($this->getVal($rowArray, 'total_retail_parts')),
                    'total_retail_oil' => $this->cleanNumeric($this->getVal($rowArray, 'total_retail_oil')),
                    'total_amount' => $this->cleanNumeric($this->getVal($rowArray, 'total_amount')),
                    'benefit_amount' => $this->cleanNumeric($this->getVal($rowArray, 'benefit')),
                    'total_payment' => $this->cleanNumeric($this->getVal($rowArray, 'total_payment')),
                    'balance' => $this->cleanNumeric($this->getVal($rowArray, 'balance')),
                ]);
                $this->importedCount++;
                $this->currentServiceCategoryCode = $this->getVal($rowArray, 'service_category');
            
            } elseif (empty($invoiceNo) && !$this->currentService) {
                return;
            }

            // DETAIL
            if ($this->currentService) {
                $this->createServiceDetail($this->currentService, $rowArray, $rowIndex);
            }

        } catch (\Exception $e) {
            Log::error("Import Error Baris {$rowIndex}: " . $e->getMessage());
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