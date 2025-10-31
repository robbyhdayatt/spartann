<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\Lokasi;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class ServiceImport implements OnEachRow, WithChunkReading
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $skippedDealerCount = 0;
    private $skippedDuplicateCount = 0; // ++ BARU: Lacak duplikat

    private $currentService = null;
    private $currentServiceCategoryCode = null;

    private $userDealerCode;
    private $lokasiMapping = [];
    private $convertMapping = [];

     public function __construct(string $userDealerCode)
     {
         $this->userDealerCode = $userDealerCode;
         $this->lokasiMapping = Lokasi::pluck('id', 'kode_lokasi')->toArray();

         $this->convertMapping = DB::table('converts')
                                     ->get()
                                     ->keyBy(function ($item) {
                                         return $this->normalizeString($item->nama_job);
                                     })
                                     ->toArray();
     }

     // ... (fungsi normalizeString, parseDate, cleanNumeric tetap sama) ...
     private function normalizeString($value)
     {
         if (!is_string($value)) {
             return $value;
         }
         $value = str_replace(['–', '—', '−', '‒', '―'], '-', $value);
         $value = preg_replace('/[\h\s\p{Zs}]+/u', ' ', $value);
         $value = preg_replace('/[^\P{Cc}\t\n\r]/u', '', $value); // Jaga \t, \n, \r
         return trim($value);
     }

     private function parseDate($dateValue)
     {
         if (is_numeric($dateValue)) {
              if ($dateValue > 2958465) {
                   try {
                       if ($dateValue > 1000000000000) {
                           return \Carbon\Carbon::createFromTimestampMs($dateValue)->format('Y-m-d');
                       } else {
                           return \Carbon\Carbon::createFromTimestamp($dateValue)->format('Y-m-d');
                       }
                   } catch (\Exception $e) {
                       Log::warning("Gagal mengurai nilai numerik {$dateValue} sebagai timestamp. Mencoba fallback Excel...");
                       try {
                           return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
                       } catch (\Exception $ex) {
                           Log::error("Gagal total mengurai tanggal numerik {$dateValue}. Error: " . $ex->getMessage());
                           return null;
                       }
                   }
              }
              try {
                 return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
              } catch (\Exception $e) {
                 Log::error("Error parsing Excel numeric date {$dateValue}: " . $e->getMessage());
                 return null;
              }
         }
         try {
             $dateValue = trim(str_replace('"', '', $dateValue));
             return \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue)->format('Y-m-d');
         } catch (\Exception $e) {
              try {
                   return \Carbon\Carbon::createFromFormat('m/d/Y', $dateValue)->format('Y-m-d');
              } catch (\Exception $e2) {
                   Log::warning("Gagal mengurai tanggal string '{$dateValue}' dengan format d/m/Y atau m/d/Y.");
                   return null;
              }
         }
     }

     private function cleanNumeric($value)
     {
         if (is_numeric($value)) {
             return floatval($value);
         }
         $cleaned = preg_replace('/[Rp. ]/', '', strval($value));
         $cleaned = str_replace(',', '.', $cleaned);
         $cleaned = preg_replace('/[^0-9.-]/', '', $cleaned);
         return is_numeric($cleaned) ? floatval($cleaned) : 0;
     }

    // ... (fungsi createServiceDetail tetap sama) ...
    private function createServiceDetail(Service $service, array $row, int $rowIndex)
    {
        $hasActivity = false;
        $serviceCategoryCode_idx = 18; // S
        $servicePackageName_idx = 21; // V
        $laborCostService_idx = 22; // W
        $partsNo_idx = 23;      // X
        $partsName_idx = 24;     // Y
        $partsQty_idx = 25;      // Z
        $partsPrice_idx = 26;    // AA
        $serviceCategoryCode = $this->currentServiceCategoryCode;
        if (empty($serviceCategoryCode)) {
            $serviceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
            Log::warning("currentServiceCategoryCode kosong di baris {$rowIndex}, menggunakan dari baris: {$serviceCategoryCode}");
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

        if (!empty($servicePackageNameNormalized)) {
            // 1. Cek KONVERSI
            if (isset($this->convertMapping[$servicePackageNameNormalized])) {
                $convertData = $this->convertMapping[$servicePackageNameNormalized];
                try {
                    $service->details()->create([
                        'item_category' => 'PART',
                        'service_category_code' => $serviceCategoryCode,
                        'service_package_name' => null,
                        'labor_cost_service' => 0,
                        'item_code' => $convertData->part_code,
                        'item_name' => $convertData->part_name,
                        'quantity' => $convertData->quantity,
                        'price' => $convertData->harga_jual,
                    ]);
                    $hasActivity = true;
                    Log::info("Baris {$rowIndex}: Konversi berhasil untuk '{$servicePackageNameRaw}' menjadi PART '{$convertData->part_code}'.");
                } catch (\Exception $e) {
                    Log::error("Baris {$rowIndex}: Gagal membuat detail konversi untuk '{$servicePackageNameRaw}'. Error: " . $e->getMessage());
                    $this->skippedCount++;
                }
            }
            // 2. Jika bukan konversi DAN ada biaya Jasa, anggap sebagai JASA biasa
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
                    ]);
                    $hasActivity = true;
                    Log::info("Baris {$rowIndex}: Menambahkan JASA '{$servicePackageNameRaw}' dengan Labor Cost Service: {$cleanedLaborCostService} (item code/name dikosongkan).");
                } catch (\Exception $e) {
                    Log::error("Baris {$rowIndex}: Gagal membuat detail JASA untuk '{$servicePackageNameRaw}'. Error: " . $e->getMessage());
                    $this->skippedCount++;
                }
            } else {
                 Log::info("Baris {$rowIndex}: Nama paket '{$servicePackageNameRaw}' ada tapi tidak ditemukan di konversi dan tidak ada biaya labor.");
            }
        }

        // --- Logika untuk PART atau OLI ---
        if (!empty($partsNo) && !empty($partsName)) {
            $cleanedPartsQty = $this->cleanNumeric($partsQty);
            $cleanedPartsPrice = $this->cleanNumeric($partsPrice);
            $itemCategory = (stripos($partsName, 'oli') !== false || stripos($partsName, 'yamalube') !== false) ? 'OLI' : 'PART';

            if ($cleanedPartsQty <= 0) {
                Log::warning("Baris {$rowIndex} (Part/Oli): Quantity part '{$partsName}' tidak valid ({$partsQty}), diatur ke 1.");
                $cleanedPartsQty = 1;
            }

            try {
                $service->details()->create([
                    'item_category' => $itemCategory,
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => null,
                    'labor_cost_service' => 0,
                    'item_code' => $partsNo,
                    'item_name' => $partsName,
                    'quantity' => $cleanedPartsQty,
                    'price' => $cleanedPartsPrice,
                ]);
                $hasActivity = true;
                Log::info("Baris {$rowIndex}: Menambahkan {$itemCategory} normal '{$partsName}'.");
            } catch (\Exception $e) {
                Log::error("Baris {$rowIndex}: Gagal membuat detail {$itemCategory} normal untuk '{$partsName}'. Error: " . $e->getMessage());
                $this->skippedCount++;
            }
        }

        if (!$hasActivity && $rowIndex > 2) {
            Log::info("Baris {$rowIndex} dilewati karena tidak ada data Jasa/Konversi atau Part/Oli yang valid untuk diproses.");
        }
    }


    /**
    * Proses per baris dari Excel.
    */
    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();

        // 1. Skip header/empty/total rows
        if ($rowIndex <= 2 || empty(array_filter($row)) || strtolower(trim($row[1] ?? '')) == 'total') {
            Log::info("Baris {$rowIndex} dilewati (header/kosong/total).");
            return;
        }

        // 2. Define column indices
        $invoiceNo_idx = 9;
        $dealerCode_idx = 2;
        $regDate_idx = 4;
        $serviceCategoryCode_idx = 18;

        // 3. Extract key data
        $invoiceNo = trim($row[$invoiceNo_idx] ?? null);
        $dealerCode = trim($row[$dealerCode_idx] ?? null);

        try {
            // 4. Validate dealer code against user
            if (!empty($dealerCode)) {
                // Cek kesesuaian dealer
                if ($dealerCode !== $this->userDealerCode) {
                    Log::warning("Baris {$rowIndex} dilewati: Dealer code '{$dealerCode}' tidak sesuai dengan user ('{$this->userDealerCode}').");
                    $this->skippedDealerCount++;
                    $this->currentService = null;
                    return; // Lanjut ke baris berikutnya
                }
                // Cek mapping lokasi
                if (!isset($this->lokasiMapping[$dealerCode])) {
                    Log::warning("Baris {$rowIndex} dilewati: Kode lokasi (Dealer code) '{$dealerCode}' tidak ditemukan di tabel Lokasi.");
                    $this->skippedCount++;
                    $this->currentService = null;
                    return; // Lanjut ke baris berikutnya
                }
            }

            // 5. Check if this is a header row (has invoice_no)
            if (!empty($invoiceNo)) {
                Log::info("Baris {$rowIndex}: Memproses header untuk Invoice '{$invoiceNo}'.");

                // ++ PERBAIKAN VALIDASI: Cek duplikat berdasarkan invoice_no AND dealer_code ++
                $serviceExists = Service::where('invoice_no', $invoiceNo)
                                       ->where('dealer_code', $dealerCode)
                                       ->exists();

                if ($serviceExists) {
                    // Invoice ini sudah ada di database.
                    Log::warning("Baris {$rowIndex} DILEWATI (DUPLIKAT): Invoice '{$invoiceNo}' untuk Dealer '{$dealerCode}' sudah ada di database.");
                    $this->skippedDuplicateCount++; // Lacak duplikat
                    $this->currentService = null; // Set currentService ke null agar baris detail di bawahnya tidak diproses
                    $this->currentServiceCategoryCode = null;
                    return; // Lanjut ke baris data berikutnya
                }
                // -- AKHIR PERBAIKAN --

                // Jika lolos validasi (tidak duplikat), baru buat service baru
                Log::info("Invoice '{$invoiceNo}' belum ada, membuat record service baru.");
                $regDate = $this->parseDate($row[$regDate_idx]);
                if (empty($regDate)) {
                    throw new \Exception("Tanggal registrasi tidak valid atau kosong ('{$row[$regDate_idx]}')");
                }

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
                    'technician_name' => $row[43] ?? null, // Index Kolom AR
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
                $this->importedCount++; // Hitung sebagai imported HANYA jika service baru dibuat

                $this->currentServiceCategoryCode = $row[$serviceCategoryCode_idx] ?? null;
                if(empty($this->currentServiceCategoryCode)) {
                    Log::warning("Baris {$rowIndex} (Header Invoice): Service Category Code (Kolom S) kosong.");
                }
            }
            // 6. Handle detail row (no invoice_no)
            elseif (empty($invoiceNo) && !$this->currentService) {
                // Jika baris ini tidak punya invoice_no DAN kita sudah men-skip header sebelumnya (currentService = null)
                // maka baris detail ini juga harus di-skip.
                Log::warning("Baris {$rowIndex} dilewati: Baris detail milik invoice yang sudah di-skip (duplikat).");
                $this->skippedCount++; // Hitung sebagai skipped biasa
                return; // Lanjut ke baris berikutnya
            }

            // 7. Proses detail jika currentService valid
            if ($this->currentService) {
                $this->createServiceDetail($this->currentService, $row, $rowIndex);
            } else {
                 Log::error("Baris {$rowIndex}: currentService null saat mencoba memproses detail ( seharusnya sudah di-handle).");
                 $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Baris {$rowIndex} dilewati karena error: " . $e->getMessage() . " | InvoiceNo: " . ($invoiceNo ?? 'N/A') . " | Dealer: " . ($dealerCode ?? 'N/A'));
            $this->skippedCount++;
            if (!empty($invoiceNo)) {
                // Reset jika error terjadi pada header row
                $this->currentService = null;
                 $this->currentServiceCategoryCode = null;
            }
        }
    }

    // ... (getter functions) ...
    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getSkippedDealerCount(): int { return $this->skippedDealerCount; }

    // ++ BARU: Getter untuk duplikat
    public function getSkippedDuplicateCount(): int { return $this->skippedDuplicateCount; }

    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}
