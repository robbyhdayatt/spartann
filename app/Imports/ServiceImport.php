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

// Hapus "WithHeadingRow"
class ServiceImport implements OnEachRow, WithChunkReading
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $skippedDealerCount = 0;
    
    // Variabel untuk melacak data dari baris header
    private $currentService = null;
    private $currentServiceCategoryCode = null;

    private $userDealerCode;
    private $lokasiMapping = [];

    // Properti untuk menyimpan data konversi
    private $convertMapping = [];

    /**
     * ++ FUNGSI BARU: Untuk membersihkan string dari karakter tersembunyi ++
     */
    private function normalizeString($value)
    {
        if ($value === null) return null;
        // Ganti dash non-standar (en-dash, em-dash) dengan dash standar
        $value = str_replace(['–', '—'], '-', $value);
        // Hapus karakter non-printable (termasuk non-breaking space \u{00A0})
        $value = preg_replace('/[^\x20-\x7E]/u', '', $value);
        // Hapus spasi ganda dan trim
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    public function __construct(string $userDealerCode)
    {
        $this->userDealerCode = $userDealerCode;
        $this->lokasiMapping = Lokasi::pluck('id', 'kode_gudang')->toArray();
        
        // ++ MODIFIKASI: Ambil data konversi dan petakan menggunakan FUNGSI NORMALISASI ++
        $this->convertMapping = DB::table('converts')
                                ->get()
                                ->keyBy(function ($item) {
                                    return $this->normalizeString($item->nama_job);
                                })
                                ->toArray();
    }

    private function parseDate($dateValue)
    {
        if (is_numeric($dateValue)) {
            return Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
        }
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function cleanNumeric($value)
    {
        if (is_numeric($value)) {
            return floatval($value);
        }
        $cleaned = preg_replace('/[Rp. ]/', '', strval($value));
        $cleaned = str_replace(',', '.', $cleaned);
        return is_numeric($cleaned) ? floatval($cleaned) : 0;
    }

    /**
     * Helper untuk membuat detail (Jasa dan Part) dari satu baris
     */
    private function createServiceDetail(Service $service, array $row, int $rowIndex)
    {
        $hasActivity = false;
        
        $servicePackageName_idx = 21;
        $laborCost_idx = 22;
        $partsNo_idx = 23;
        $partsName_idx = 24;
        $partsQty_idx = 25;
        $partsPrice_idx = 26;

        $serviceCategoryCode = $this->currentServiceCategoryCode;
        
        // ++ MODIFIKASI: Gunakan FUNGSI NORMALISASI saat mengambil data dari Excel ++
        $servicePackageName = $this->normalizeString($row[$servicePackageName_idx] ?? null);
        $laborCost = $row[$laborCost_idx] ?? null;

        // Cek Jasa
        // Logika override sekarang akan membandingkan string yang sudah dinormalisasi
        if (isset($this->convertMapping[$servicePackageName])) {
            
            // Logika override (JIKA DITEMUKAN)
            $convertData = $this->convertMapping[$servicePackageName];
            
            $service->details()->create([
                'item_category' => 'JASA', 
                'service_category_code' => $serviceCategoryCode,
                'service_package_name' => $row[$servicePackageName_idx], // Simpan nama job aslinya dari Excel
                'item_code' => $convertData->part_code_input, // Dari converts
                'item_name' => $convertData->part_name,       // Dari converts
                'quantity'  => $convertData->quantity,        // Dari converts
                'price'     => $convertData->harga_jual,     // Dari converts
            ]);
            $hasActivity = true;

        } else {
            
            // Logika asli (JIKA TIDAK DITEMUKAN)
            if (!empty($servicePackageName) && $laborCost !== null) {
                $service->details()->create([
                    'item_category' => 'JASA', 
                    'service_category_code' => $serviceCategoryCode,
                    'service_package_name' => $row[$servicePackageName_idx], // Nama asli dari Excel
                    'item_code' => $servicePackageName, // Gunakan nama sbg kode
                    'item_name' => $servicePackageName,
                    'quantity' => 1,
                    'price' => $this->cleanNumeric($laborCost),
                ]);
                $hasActivity = true;
            }
        }

        // Cek Part/Oli (Logika ini tetap berjalan, tidak terpengaruh)
        $partsNo = $row[$partsNo_idx] ?? null;
        $partsName = $row[$partsName_idx] ?? null;

        if (!empty($partsNo) && !empty($partsName)) {
            $service->details()->create([
                'item_category' => (strpos(strtolower($partsName), 'oli') !== false || strpos(strtolower($partsName), 'yamalube') !== false) ? 'OLI' : 'PART',
                'service_category_code' => $serviceCategoryCode,
                'service_package_name' => null,
                'item_code' => $partsNo,
                'item_name' => $partsName,
                'quantity' => $this->cleanNumeric($row[$partsQty_idx] ?? 1),
                'price' => $this->cleanNumeric($row[$partsPrice_idx] ?? 0),
            ]);
            $hasActivity = true;
        }

        if (!$hasActivity && $rowIndex > 2) {
            Log::info("Baris {$rowIndex} tidak memiliki item Jasa atau Part, dilewati.");
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();

        if ($rowIndex <= 2 || empty($row[2]) || strtolower($row[1] ?? '') == 'total') {
            return;
        }

        $invoiceNo = trim($row[9] ?? null);
        $dealerCode = trim($row[2] ?? null);

        try {
            if (!empty($dealerCode)) {
                if ($dealerCode !== $this->userDealerCode) {
                    $this->skippedDealerCount++;
                    $this->currentService = null;
                    return;
                }
                if (!isset($this->lokasiMapping[$dealerCode])) {
                    Log::warning("Skipping invoice: Dealer code '{$dealerCode}' not found.");
                    $this->skippedCount++;
                    $this->currentService = null;
                    return;
                }
            }
            
            if (!empty($invoiceNo)) {
                $service = Service::where('invoice_no', $invoiceNo)->first();
                
                if ($service) {
                    $this->currentService = $service;
                } else {
                    $regDate = $this->parseDate($row[4]);
                    if (empty($regDate)) {
                         throw new \Exception("Data header tidak lengkap (tanggal tidak valid) di baris {$rowIndex}.");
                    }
                    
                    $this->currentService = Service::create([
                        // Info Utama
                        'invoice_no'        => $invoiceNo,
                        'reg_date'          => $regDate,
                        'dealer_code'       => $dealerCode,
                        'lokasi_id'         => $this->lokasiMapping[$dealerCode] ?? null,
                        'yss'               => $row[1] ?? null,
                        'point'             => $row[3] ?? null,
                        'service_order'     => $row[5] ?? null,
                        'plate_no'          => $row[6] ?? null,
                        'work_order_no'     => $row[7] ?? null,
                        'work_order_status' => $row[8] ?? null,
                        'technician_name'   => $row[43] ?? null, // index 43

                        // Info Customer
                        'customer_name'      => $row[10] ?? null,
                        'customer_ktp'       => $row[11] ?? null,
                        'customer_npwp_no'   => $row[12] ?? null,
                        'customer_npwp_name' => $row[13] ?? null,
                        'customer_phone'     => $row[14] ?? null,
                        
                        // Info Kendaraan
                        'mc_brand'      => $row[15] ?? null,
                        'mc_model_name' => $row[16] ?? null,
                        'mc_frame_no'   => $row[17] ?? null,

                        // Info Pembayaran
                        'payment_type'       => $row[27] ?? null,
                        'transaction_code'   => $row[28] ?? null,
                        'e_payment_amount'   => $this->cleanNumeric($row[30] ?? 0),
                        'cash_amount'        => $this->cleanNumeric($row[31] ?? 0),
                        'debit_amount'       => $this->cleanNumeric($row[32] ?? 0),
                        
                        // Info Total Rincian
                        'total_down_payment' => $this->cleanNumeric($row[33] ?? 0),
                        'total_labor'        => $this->cleanNumeric($row[34] ?? 0),
                        'total_part_service' => $this->cleanNumeric($row[35] ?? 0),
                        'total_oil_service'  => $this->cleanNumeric($row[36] ?? 0),
                        'total_retail_parts' => $this->cleanNumeric($row[37] ?? 0),
                        'total_retail_oil'   => $this->cleanNumeric($row[38] ?? 0), 
                        
                        // Info Total Final
                        'total_amount'   => $this->cleanNumeric($row[39] ?? 0),
                        'benefit_amount' => $this->cleanNumeric($row[40] ?? 0),
                        'total_payment'  => $this->cleanNumeric($row[41] ?? 0),
                        'balance'        => $this->cleanNumeric($row[42] ?? 0),
                    ]);
                    
                    $this->importedCount++;
                }
                
                $this->currentServiceCategoryCode = $row[18] ?? null; // Simpan 'KSB'
            
            } elseif (empty($invoiceNo) && !$this->currentService) {
                throw new \Exception("Baris detail 'yatim' di baris {$rowIndex}.");
            }

            if ($this->currentService) {
                $this->createServiceDetail($this->currentService, $row, $rowIndex);
            }

        } catch (\Exception $e) {
            Log::error("Baris {$rowIndex} dilewati: " . $e->getMessage());
            $this->skippedCount++;
            $this->currentService = null;
        }
    }
    
    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getSkippedDealerCount(): int { return $this->skippedDealerCount; }
    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}