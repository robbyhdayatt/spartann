# 🏍️ SPARTAN LTI — Sistem Manajemen Sparepart, Inventori & Bengkel Dealer Yamaha

![Laravel Version](https://img.shields.io/badge/Laravel-8.x-orange.svg)
![PHP Version](https://img.shields.io/badge/PHP-%5E7.3%20%7C%20%5E8.0-blue.svg)
![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-darkblue.svg)
![AdminLTE](https://img.shields.io/badge/UI-AdminLTE%203-green.svg)
![Organization](https://img.shields.io/badge/Watermark-IT%20Lautan%20Teduh-blue.svg)
![Developer](https://img.shields.io/badge/Developer-Robby%20Hidayat-success.svg)

**SPARTAN LTI** (*Sparepart & Inventory Management System*) adalah aplikasi Enterprise Resource Planning (ERP) berstandar industri yang dirancang khusus untuk mengelola operasional **Main Dealer Yamaha** beserta **34+ jaringan dealer & bengkel resmi** di wilayah Lampung.

---

## 📌 Fitur & Modul Utama

- **📦 Manajemen Inventori Multi-Lokasi & Multi-Rak (WMS)**
  - Melacak stok barang di 34+ cabang (Pusat, Gudang, Dealer).
  - Manajemen rak berbasis koordinat 4 level: `[Zona]-[NomorRak]-[Level]-[Bin]` (contoh: `A-R01-L1-B01`).
  - Pemisahan lokasi rak **PENYIMPANAN** vs **KARANTINA**.
  - Metode pencatatan stok **FIFO (First-In, First-Out)** dengan **Pessimistic Locking** (`lockForUpdate()`).

- **🛒 Purchasing & Distribution Management**
  - **Dealer Request**: Permintaan pasokan stok dari cabang ke Gudang Pusat.
  - **Supplier PO**: Pemesanan barang dari Gudang ke Supplier / Vendor resmi.
  - Alur otorisasi berjenjang (2-level approval).

- **🔬 Quality Control (QC) & Receiving Workflow**
  - Penerimaan barang masuk (*Goods Receipt*).
  - Inspeksi QC: Barang lolos langsung masuk proses **Putaway** ke rak penyimpanan; barang retur/cacat masuk ke rak **KARANTINA**.
  - Alur penanganan barang karantina (Restock, Return ke Supplier, atau Write-Off).

- **💰 Point of Sale (POS) & Retail Sales**
  - Kasir POS retail terintegrasi dengan pemotongan stok batch FIFO otomatis.
  - Kalkulasi PPN (11%) dan diskon promosi bertingkat.
  - Cetak Faktur Penjualan format landscape PDF (24cm x 14cm).

- **🔧 Service Bengkel / Workshop Management**
  - Integrasi invoice service bengkel (data kendaraan, nomor rangka, nomor mesin, teknisi).
  - Import harian data service dari sistem YSS/Yamaha via Excel.
  - Estimasi dan pemotongan otomatis stok sparepart yang digunakan dalam servis.

- **🔄 Mutasi & Penyesuaian Stok (Stock Transfer & Adjustment)**
  - Transfer stok antar-cabang dengan status `IN_TRANSIT` dan penerimaan parsial.
  - Penyesuaian stok (*Stock Adjustment* `TAMBAH` / `KURANG`) dengan approval workflow.

- **📊 Laporan & Keuangan lengkap (Reports)**
  - Kartu Stok (*Stock Card*), Total Stok, Stok per Lokasi, Jurnal Penjualan, Jurnal Pembelian, Nilai Inventori (HPP), Ringkasan Penjualan, dan Service Summary.
  - Export data laporan ke format Microsoft Excel (.xlsx).

---

## 🔐 Otorisasi & Peran Pengguna (10 Roles)

Sistem menggunakan kontrol akses berbasis peran (*Gate Authorized*) yang dibagi ke dalam 10 jabatan:

| Singkatan | Nama Jabatan | Lingkup Otoritas |
|-----------|--------------|------------------|
| **SA** | Super Admin | Akses Penuh Sistem (Global) |
| **PIC** | Person In Charge | Monitoring Area & Approval Otoritas |
| **ASD** | Area Service Development | Pengawasan Bengkel & Layanan Service |
| **IMS** | Inventory MD | Pengelolaan Master Data & Inventori Pusat |
| **ACC** | Accounting MD | Laporan Keuangan & Audit Jurnal |
| **KG** | Kepala Gudang | Otorisasi Receiving, QC, Putaway & Mutasi |
| **AG** | Admin Gudang | Eksekusi Fisik Gudang & Penerimaan |
| **KC** | Kepala Cabang | Approval Internal Dealer & Cabang |
| **PC** | Part Counter | Transaksi POS & Stok Cabang |
| **KSR** | Kasir | Penjualan Kasir POS |

---

## 🛠️ Technology Stack

- **Framework**: Laravel 8.x
- **PHP Version**: PHP 7.3 - 8.0+
- **Frontend Template**: AdminLTE v3.1 + Bootstrap 5
- **Database**: MySQL / MariaDB (Collation: `utf8mb4_unicode_ci`)
- **PDF Engine**: DomPDF (`barryvdh/laravel-dompdf`)
- **Spreadsheet Engine**: Laravel Excel (`maatwebsite/excel`)
- **DataTables Engine**: Yajra DataTables (`yajra/laravel-datatables-oracle`)
- **API Authentication**: Laravel Sanctum

---

## 🚀 Panduan Instalasi Lokal (Laragon / XAMPP)

### 1. Prasyarat Sistem
- PHP >= 7.3 / 8.0 (Extension: `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `fileinfo`)
- Composer >= 2.0
- Node.js & NPM
- Laragon / XAMPP dengan MySQL Server

### 2. Langkah Instalasi

1. **Clone / Buka Repositori**:
   ```bash
   cd c:/laragon/www/spartann
   ```

2. **Install Dependensi PHP & JS**:
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**:
   Salin `.env.example` ke `.env` dan atur koneksi database:
   ```env
   APP_NAME="SPARTAN LTI"
   APP_ENV=local
   APP_KEY=base64:...
   APP_DEBUG=true
   APP_URL=http://localhost/spartann

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=spartann
   DB_USERNAME=root
   DB_PASSWORD=

   QUEUE_CONNECTION=sync
   ```

4. **Import Database**:
   Import file SQL bawaan `spartann.sql` ke MySQL Database `spartann` melalui phpMyAdmin atau MySQL CLI:
   ```bash
   mysql -u root -p spartann < spartann.sql
   ```

5. **Jalankan Migrasi Tambahan**:
   ```bash
   php artisan migrate
   ```

6. **Build Asset Frontend**:
   ```bash
   npm run dev
   ```

---

## ⚙️ Perintah Artisan Khusus

- **Deaktivasi Kampanye Kedaluwarsa**:
  ```bash
  php artisan campaigns:deactivate
  ```
- **Audit Kartu Stok / Rebuild Running Balance**:
  ```bash
  php artisan stock:fix-history
  ```
- **Kalkulasi Ulang HPP Beli Rata-Rata**:
  ```bash
  php artisan stock:recalculate-avg-price
  ```

---

## 📄 Lisensi & Hak Cipta

Hak Cipta © 2026 **SPARTAN LTI** - All Rights Reserved.  
🏢 **Developed & Maintained by**: **Robby Hidayat**  
💧 **Watermark / Organization**: **IT Lautan Teduh**  

*Sistem dikembangkan secara internal oleh Tim IT Lautan Teduh untuk mengoperasikan jaringan Main Dealer & Dealer Resmi Yamaha di wilayah Lampung.*
