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
use Carbon\Carbon;

class ServiceImport implements OnEachRow, WithChunkReading
{
    // Counters
    private $importedCount = 0;
    private $updatedCount = 0;
    private $skippedCount = 0;
    private $skippedDealerCount = 0;
    private $skippedDuplicateCount = 0;

    // State
    private $currentService = null;
    private $processedDetailIds = []; // Track detail yang valid agar tidak terhapus
    private $currentServiceCategoryCode = null;
    
    // Config & Cache
    private $userDealerCode;
    private $userId;
    private $lokasiMapping = [];
    private $convertMapping = [];
    private $errorMessages = [];
    
    // Header & Date
    private $headerRowDetected = false;
    private $colMap = [];
    private $referenceRegDate = null;

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

    // --- CLEANUP ---
    public function __destruct()
    {
        if ($this->currentService) {
            $this->cleanupOrphanDetails($this->currentService);
        }
    }

    private function cleanupOrphanDetails(Service $service)
    {
        // Hapus detail yang ada di DB tapi TIDAK ada di Excel import kali ini
        // Ini menangani kasus item dihapus atau berubah tipe (Jasa -> Part)
        $detailsToDelete = $service->details()
            ->whereNotIn('id', $this->processedDetailIds)
            ->get();

        foreach ($detailsToDelete as $detail) {
            // Jika detail yang dihapus adalah Barang, kembalikan stoknya
            if ($detail->barang_id && $detail->quantity > 0) {
                // Pass quantity negatif untuk refund stok
                $this->processStockDeduction(
                    $detail->barang_id, 
                    -($detail->quantity), // Negatif = Refund
                    $service->id, 
                    $service->lokasi_id, 
                    $service->invoice_no, 
                    $service->created_at
                );
            }
            $detail->delete();
        }
    }

    // --- GETTERS ---
    public function getErrorMessages() { return $this->errorMessages; }
    public function getImportedCount(): int { return $this->importedCount; }
    public function getUpdatedCount(): int { return $this->updatedCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getSkippedDealerCount(): int { return $this->skippedDealerCount; }
    public function getSkippedDuplicateCount(): int { return $this->skippedDuplicateCount; }
    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }

    // --- HELPERS ---
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
        if (is_numeric($dateValue)) {
            try {
               return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
        try {
            return Carbon::createFromFormat('d/m/Y', trim(str_replace('"', '', $dateValue)))->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse($dateValue)->format('Y-m-d');
            } catch (\Exception $e) { return null; }
        }
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) return floatval($value);
        if (empty($value)) return 0;
        $str = strval($value);
        $str = preg_replace('/[Rp\s]/i', '', $str);
        if (preg_match('/\.0+$/', $str)) $str = preg_replace('/\.0+$/', '', $str);
        $isNegative = (strpos($str, '-') !== false);
        $str = preg_replace('/[^0-9.,]/', '', $str);
        if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
            $str = str_replace('.', '', $str); 
            $str = str_replace(',', '.', $str); 
        } elseif (strpos($str, ',') !== false) {
             $str = str_replace(',', '.', $str);
        } elseif (strpos($str, '.') !== false) {
             $str = str_replace('.', '', $str);
        }
        $val = floatval($str);
        return $isNegative ? -$val : $val;
    }

    private function detectHeaderRow(array $row)
    {
        $rowSlugs = array_map(function($item) { return Str::slug(trim((string)$item), '_'); }, $row);
        if (!in_array('invoice_no', $rowSlugs) && !in_array('no_invoice', $rowSlugs)) return false;

        $possibleHeaders = [
            'invoice_no' => ['invoice_no', 'no_invoice'],
            'dealer_code' => ['dealer', 'dealer_code'],
            'reg_date' => ['reg_date', 'date', 'tanggal'],
            'service_category' => ['service_category'],
            'total_oil' => ['total_oil_service'],
            'total_amount' => ['total_amount', 'grand_total'],
            'technician' => ['technician_name'],
            'package_name' => ['service_package', 'paket_servis'],
            'labor_cost' => ['labor_cost_service'],
            'parts_no' => ['parts_no'],
            'parts_name' => ['parts_name'],
            'parts_qty' => ['parts_qty'],
            'parts_price' => ['parts_price'],
            'cust_name' => ['customer_information', 'nama_customer', 'nama'],
            'cust_ktp' => ['ktp', 'nik'],
            'cust_npwp_no' => ['no_npwp', 'npwp'],
            'cust_npwp_name' => ['name_npwp', 'nama_npwp'],
            'cust_phone' => ['telepon_no', 'phone'],
            'mc_brand' => ['brand', 'merk'],
            'mc_model' => ['model_name', 'tipe_motor'],
            'mc_frame' => ['frame_no', 'no_rangka'],
            'dp' => ['down_payment_dp', 'dp'],
            'payment_type' => ['payment_type'],
            'trans_code' => ['transaction_code'],
            'e_payment' => ['e_payment_amount'],
            'cash' => ['cash_amount'],
            'debit' => ['debit_amount'],
            'total_labor' => ['total_labor'],
            'total_part' => ['total_part_service'],
            'total_retail_parts' => ['total_retail_parts'],
            'total_retail_oil' => ['total_retail_oil'],
            'benefit' => ['benefit_amount'],
            'total_payment' => ['total_payment'],
            'balance' => ['balance'],
            'yss' => ['yss'], 'point' => ['point'], 'service_order' => ['service_order'],
            'plate_no' => ['plate_no'], 'work_order' => ['no_work_order'], 'wo_status' => ['status_work_order'],
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
        $this->headerRowDetected = true;
        return true;
    }

    private function getVal($row, $key, $default = null)
    {
        $defaultIndex = [
            'dealer_code' => 2, 'invoice_no' => 9, 'reg_date' => 4,
            'cust_name' => 10, 'cust_ktp' => 11, 'cust_npwp_no' => 12, 'cust_npwp_name' => 13, 'cust_phone' => 14,
            'mc_brand' => 15, 'mc_model' => 16, 'mc_frame' => 17,
            'service_category' => 18, 'package_name' => 21, 'labor_cost' => 22,
            'parts_no' => 23, 'parts_name' => 24, 'parts_qty' => 25, 'parts_price' => 26,
            'payment_type' => 27, 'trans_code' => 28,
            'e_payment' => 30, 'cash' => 31, 'debit' => 32, 'dp' => 33, 
            'total_labor' => 34, 'total_part' => 35, 'total_oil' => 36, 
            'total_retail_parts' => 37, 'total_retail_oil' => 38, 'total_amount' => 39,
            'benefit' => 40, 'total_payment' => 41, 'balance' => 42, 'technician' => 43,
            'yss' => 1, 'point' => 3, 'service_order' => 5, 'plate_no' => 6,
            'work_order' => 7, 'wo_status' => 8
        ];
        $index = $this->colMap[$key] ?? ($defaultIndex[$key] ?? -1);
        return ($index >= 0 && isset($row[$index])) ? $row[$index] : $default;
    }

    // --- CORE LOGIC: STOCK MANAGEMENT (SAFE) ---
    private function processStockDeduction($barangId, $qty, $serviceId, $lokasiId, $invoiceNo, $customCreatedAt = null)
    {
        if (!$barangId || !$lokasiId || $qty == 0) return 0;

        $barangMaster = Barang::find($barangId);
        $namaBarang = $barangMaster->part_name ?? 'Unknown Item';
        $timestamp = $customCreatedAt ?? now();

        // 1. JIKA QTY > 0 (PENJUALAN / POTONG STOK)
        if ($qty > 0) {
            $batches = InventoryBatch::where('barang_id', $barangId)
                ->where('lokasi_id', $lokasiId)
                ->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate() // Lock agar tidak balapan
                ->get();

            $sisaQty = $qty;
            $totalCost = 0;

            foreach ($batches as $batch) {
                if ($sisaQty <= 0) break;
                
                $stokSaatIni = $batch->quantity;
                $potong = min($stokSaatIni, $sisaQty);
                
                $costPerUnit = $barangMaster->selling_out; 
                $totalCost += ($costPerUnit * $potong);
                
                // Update Stok
                $batch->quantity -= $potong;
                $batch->save();

                // Catat History
                StockMovement::create([
                    'barang_id' => $barangId, 'lokasi_id' => $lokasiId, 'rak_id' => $batch->rak_id,
                    'jumlah' => -$potong,
                    'stok_sebelum' => $stokSaatIni, 
                    'stok_sesudah' => $stokSaatIni - $potong,
                    'referensi_type' => 'App\Models\Service', 'referensi_id' => $serviceId,
                    'keterangan' => "Pemakaian Service #{$invoiceNo} ({$namaBarang})",
                    'user_id' => $this->userId,
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
                $sisaQty -= $potong;
            }
            return ($qty > 0) ? ($totalCost / $qty) : 0;
        } 
        // 2. JIKA QTY < 0 (REFUND / KEMBALIKAN STOK)
        else {
            $qtyToRestore = abs($qty);
            
            $batch = InventoryBatch::where('barang_id', $barangId)
                ->where('lokasi_id', $lokasiId)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()
                ->first();

            if ($batch) {
                $stokAwal = $batch->quantity;
                $batch->increment('quantity', $qtyToRestore);
                $stokAkhir = $batch->quantity;
                $rakId = $batch->rak_id;
            } else {
                // Create new batch if not exists
                $stokAwal = 0;
                $batch = InventoryBatch::create([
                    'barang_id' => $barangId, 'lokasi_id' => $lokasiId, 'quantity' => $qtyToRestore
                ]);
                $stokAkhir = $qtyToRestore;
                $rakId = $batch->rak_id;
            }

            StockMovement::create([
                'barang_id' => $barangId, 'lokasi_id' => $lokasiId, 'rak_id' => $rakId,
                'jumlah' => $qtyToRestore,
                'stok_sebelum' => $stokAwal, 
                'stok_sesudah' => $stokAkhir,
                'referensi_type' => 'App\Models\Service', 'referensi_id' => $serviceId,
                'keterangan' => "Koreksi/Refund Service #{$invoiceNo} ({$namaBarang})",
                'user_id' => $this->userId,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
            return 0;
        }
    }

    // --- SMART SYNC (KUNCI PEMECAHAN MASALAH) ---
    private function syncServiceDetail($service, $type, $data)
    {
        $lokasiId = $service->lokasi_id;
        $serviceDate = $service->created_at;

        // Cari detail yang identik (Barang sama ATAU Jasa sama)
        $query = $service->details()->where('item_category', $type);

        $barangId = null;
        if ($type == 'PART' || $type == 'OLI') {
            $barang = Barang::where('part_code', $data['item_code'])->first();
            if ($barang) {
                $barangId = $barang->id;
                $query->where('barang_id', $barangId);
            }
        } else {
            // Untuk JASA, cari berdasarkan service_package_name bukan item_name
            $query->where('service_package_name', $data['service_package_name']);
        }

        // Ambil detail yang belum diproses di sesi ini
        $existingDetail = $query->whereNotIn('id', $this->processedDetailIds)->first();

        if ($existingDetail) {
            // --- UPDATE MODE ---
            $this->processedDetailIds[] = $existingDetail->id;

            // Cek apakah QTY berubah?
            $qtyDiff = $data['quantity'] - $existingDetail->quantity;

            if ($qtyDiff != 0 && $barangId) {
                // Ada perubahan qty -> Koreksi stok
                // Jika positif: Potong lagi. Jika negatif: Refund.
                $this->processStockDeduction($barangId, $qtyDiff, $service->id, $lokasiId, $service->invoice_no, $serviceDate);
            }
            // JIKA QTY SAMA, KITA TIDAK SENTUH STOK -> HISTORY BERSIH

            // Update data nominal/nama tanpa ganggu stok
            $existingDetail->update([
                'service_category_code' => $data['service_category_code'],
                'service_package_name' => $data['service_package_name'],
                'labor_cost_service' => $data['labor_cost_service'],
                'item_code' => $data['item_code'],
                'item_name' => $data['item_name'],
                'quantity' => $data['quantity'],
                'price' => $data['price']
            ]);

        } else {
            // --- CREATE MODE ---
            $costPrice = 0;
            if ($barangId) {
                // Item baru -> Potong stok
                $costPrice = $this->processStockDeduction($barangId, $data['quantity'], $service->id, $lokasiId, $service->invoice_no, $serviceDate);
            }

            $newDetail = $service->details()->create([
                'item_category' => $type,
                'service_category_code' => $data['service_category_code'],
                'service_package_name' => $data['service_package_name'],
                'labor_cost_service' => $data['labor_cost_service'],
                'item_code' => $data['item_code'],
                'item_name' => $data['item_name'],
                'quantity' => $data['quantity'],
                'price' => $data['price'],
                'barang_id' => $barangId,
                'cost_price' => $costPrice,
            ]);
            
            $this->processedDetailIds[] = $newDetail->id;
        }
    }

    private function processRowDetails(Service $service, array $row)
    {
        $serviceCategoryCode = $this->currentServiceCategoryCode ?? $this->getVal($row, 'service_category');
        $servicePackageNameRaw = $this->getVal($row, 'package_name');
        $servicePackageNameNormalized = $this->normalizeString($servicePackageNameRaw);
        $laborCost = $this->cleanNumeric($this->getVal($row, 'labor_cost'));
        
        $partsNo = trim($this->getVal($row, 'parts_no') ?? '');
        $partsName = trim($this->getVal($row, 'parts_name') ?? '');
        $partsQty = $this->cleanNumeric($this->getVal($row, 'parts_qty'));
        $partsPrice = $this->cleanNumeric($this->getVal($row, 'parts_price'));

        // VALIDASI: Hanya proses jika ada data yang valid di baris ini
        $hasPackageData = !empty($servicePackageNameNormalized);
        $hasLaborData = ($laborCost > 0);
        $hasPartsData = (!empty($partsNo) && !empty($partsName));
        
        // Jika tidak ada data apapun di baris ini, skip
        if (!$hasPackageData && !$hasLaborData && !$hasPartsData) {
            return;
        }

        // 1. PAKET / JASA
        if ($hasPackageData) {
            if (isset($this->convertMapping[$servicePackageNameNormalized])) {
                // CONVERT KE PART
                $conv = $this->convertMapping[$servicePackageNameNormalized];
                $this->syncServiceDetail($service, 'PART', [
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => null,
                    'labor_cost_service' => 0,
                    'item_code' => $conv->part_code,
                    'item_name' => $conv->part_name,
                    'quantity' => $conv->quantity,
                    'price' => $conv->retail
                ]);
            } else {
                // JASA MURNI - item_name dikosongkan (null)
                $this->syncServiceDetail($service, 'JASA', [
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => $servicePackageNameRaw,
                    'labor_cost_service' => $laborCost,
                    'item_code' => null,
                    'item_name' => null, // PERUBAHAN: Tidak isi item_name untuk JASA
                    'quantity' => 1,
                    'price' => $laborCost
                ]);
            }
        } elseif ($hasLaborData) {
            // JASA MANUAL - item_name dikosongkan (null)
            $this->syncServiceDetail($service, 'JASA', [
                'service_category_code' => $serviceCategoryCode,
                'service_package_name' => $servicePackageNameRaw,
                'labor_cost_service' => $laborCost,
                'item_code' => null,
                'item_name' => null, // PERUBAHAN: Tidak isi item_name untuk JASA
                'quantity' => 1,
                'price' => $laborCost
            ]);
        }

        // 2. PART MANUAL
        if ($hasPartsData) {
            $cat = (stripos($partsName, 'oli') !== false || stripos($partsName, 'yamalube') !== false) ? 'OLI' : 'PART';
            $this->syncServiceDetail($service, $cat, [
                'service_category_code' => $serviceCategoryCode,
                'service_package_name' => null,
                'labor_cost_service' => 0,
                'item_code' => $partsNo,
                'item_name' => $partsName,
                'quantity' => $partsQty ?: 1,
                'price' => $partsPrice
            ]);
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $rowArray = $row->toArray();

        if (empty(array_filter($rowArray))) return;

        // WRAP TRANSACTION
        DB::transaction(function() use ($rowArray, $rowIndex) {
            if (!$this->headerRowDetected) {
                if ($this->detectHeaderRow($rowArray)) return; 
            }

            $rowString = implode(' ', array_slice($rowArray, 0, 10));
            if (str_contains(strtoupper($rowString), 'TOTAL')) return;

            if ($this->isRowCancelled($rowArray)) {
                Log::info("Baris {$rowIndex}: Skipped - Status Cancelled");
                return;
            }

            $invoiceNo = trim($this->getVal($rowArray, 'invoice_no') ?? '');
            $dealerCode = trim($this->getVal($rowArray, 'dealer_code') ?? '');

            try {
                if (!empty($dealerCode)) {
                    if ($dealerCode !== $this->userDealerCode) {
                        $this->skippedDealerCount++; $this->currentService = null; return;
                    }
                    if (!isset($this->lokasiMapping[$dealerCode])) {
                        $this->skippedCount++; $this->currentService = null;
                        $this->errorMessages[] = "Baris {$rowIndex}: Kode Dealer tidak dikenali.";
                        return;
                    }
                }

                if (!empty($invoiceNo)) {

                    if ($this->isRowCancelled($rowArray)) {
                        $this->currentService = null;
                        return;
                    }
                    // GANTI INVOICE: Cleanup invoice sebelumnya
                    if ($this->currentService && $this->currentService->invoice_no !== $invoiceNo) {
                        $this->cleanupOrphanDetails($this->currentService);
                        $this->processedDetailIds = [];
                        // Reset service category code untuk invoice baru
                        $this->currentServiceCategoryCode = null;
                    }

                    $existingService = Service::where('invoice_no', $invoiceNo)
                                            ->where('dealer_code', $dealerCode)
                                            ->lockForUpdate()
                                            ->first();

                    $regDate = $this->parseDate($this->getVal($rowArray, 'reg_date'));
                    if (empty($regDate)) throw new \Exception("Tanggal registrasi invalid.");

                    if ($this->referenceRegDate === null) $this->referenceRegDate = $regDate;
                    $isFileToday = ($this->referenceRegDate === now()->toDateString());
                    $shouldBackdate = !$isFileToday; 

                    $serviceData = [
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
                        'customer_npwp_no' => $this->getVal($rowArray, 'cust_npwp_no'),
                        'customer_npwp_name' => $this->getVal($rowArray, 'cust_npwp_name'),
                        'customer_phone' => $this->getVal($rowArray, 'cust_phone'),
                        'mc_brand' => $this->getVal($rowArray, 'mc_brand'),
                        'mc_model_name' => $this->getVal($rowArray, 'mc_model'),
                        'mc_frame_no' => $this->getVal($rowArray, 'mc_frame'),
                        'payment_type' => $this->getVal($rowArray, 'payment_type'),
                        'transaction_code' => $this->getVal($rowArray, 'trans_code'),
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
                    ];

                    if ($existingService) {
                        // UPDATE MODE: Smart Sync Header (Detail diurus processRowDetails)
                        $existingService->update($serviceData);
                        $this->currentService = $existingService;
                        $this->updatedCount++;
                    } else {
                        // CREATE MODE
                        $serviceData['invoice_no'] = $invoiceNo;
                        if ($shouldBackdate) {
                            $sibling = Service::where('dealer_code', $dealerCode)
                                ->where('reg_date', $regDate)->orderBy('created_at', 'asc')->first();
                            if ($sibling) {
                                $serviceData['created_at'] = $sibling->created_at;
                                $serviceData['updated_at'] = now();
                            }
                        }
                        $this->currentService = Service::create($serviceData);
                        $this->importedCount++;
                    }
                    $this->currentServiceCategoryCode = $this->getVal($rowArray, 'service_category');
                
                } elseif (empty($invoiceNo) && !$this->currentService) {
                    // Tidak ada invoice di baris ini dan tidak ada service aktif sebelumnya
                    return;
                }

                if ($this->currentService) {
                    $this->processRowDetails($this->currentService, $rowArray);
                }

            } catch (\Exception $e) {
                Log::error("Import Error Row {$rowIndex}: " . $e->getMessage());
                $this->errorMessages[] = "Row {$rowIndex}: " . $e->getMessage();
                $this->skippedCount++;
                if (!empty($invoiceNo)) $this->currentService = null;
            }
        });
    }
    private function isRowCancelled(array $row)
    {
        $woStatus = trim($this->getVal($row, 'wo_status') ?? '');
        return stripos($woStatus, 'ZZ. Cancelled') !== false || stripos($woStatus, 'Cancelled') !== false;
    }
}