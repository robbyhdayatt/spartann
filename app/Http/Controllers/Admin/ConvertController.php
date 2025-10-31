<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convert; // Model ini sekarang menunjuk ke VIEW 'converts'
use App\Models\Barang;  // Model baru 'Barang'
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // <-- Penting

class ConvertController extends Controller
{
    /**
     * Nama tabel fisik untuk operasi tulis
     */
    private $convertsMainTable = 'converts_main';

    public function __construct()
    {
        $this->middleware('can:manage-converts');
    }

    /**
     * Tampilkan daftar data dari VIEW 'converts'.
     */
    public function index()
    {
        // Ambil data dari VIEW (via Model)
        $converts = Convert::orderBy('nama_job')->get();

        // Ambil data barang untuk modal dropdown
        $barangs = Barang::orderBy('part_name')->get();

        return view('admin.converts.index', compact('converts', 'barangs'));
    }

    /**
     * Simpan data baru ke tabel 'converts_main'.
     */
    public function store(Request $request)
    {
        // Validasi berdasarkan input form BARU
        $validator = Validator::make($request->all(), [
            'nama_job' => ['required', 'string', 'max:255', Rule::unique($this->convertsMainTable)], // Validasi ke tabel fisik
            'quantity' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
            'part_code' => 'required|string|exists:barangs,part_code', // Validasi ke tabel barangs
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Tulis data ke tabel fisik 'converts_main'
            DB::table($this->convertsMainTable)->insert([
                'nama_job' => $request->nama_job,
                'quantity' => $request->quantity,
                'keterangan' => $request->keterangan,
                'part_code' => $request->part_code,
                'created_at' => now(),
                'updated_at' => now()
                // 'is_active' => true // Jika Anda menambahkan kolom ini
            ]);

            return response()->json(['success' => 'Data convert berhasil ditambahkan.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menambahkan data convert. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Ambil data dari VIEW 'converts' untuk modal edit.
     */
    public function getEditData(Convert $convert)
    {
        // $convert diambil dari VIEW, sudah berisi data join
        return response()->json($convert);
    }

    /**
     * Update data di tabel 'converts_main'.
     */
    public function update(Request $request, $id) // Terima $id, bukan model
    {
         $validator = Validator::make($request->all(), [
            'nama_job' => ['required', 'string', 'max:255', Rule::unique($this->convertsMainTable)->ignore($id)], // Validasi ke tabel fisik
            'quantity' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
            'part_code' => 'required|string|exists:barangs,part_code', // Validasi ke tabel barangs
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Update data di tabel fisik 'converts_main'
            DB::table($this->convertsMainTable)->where('id', $id)->update([
                'nama_job' => $request->nama_job,
                'quantity' => $request->quantity,
                'keterangan' => $request->keterangan,
                'part_code' => $request->part_code,
                'updated_at' => now()
            ]);

            return response()->json(['success' => 'Data convert berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal memperbarui data convert. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Hapus data dari tabel 'converts_main'.
     */
    public function destroy($id) // Terima $id
    {
        try {
            // Hapus dari tabel fisik 'converts_main'
            DB::table($this->convertsMainTable)->where('id', $id)->delete();
            return redirect()->route('admin.converts.index')->with('success', 'Data convert berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('admin.converts.index')->with('error', 'Gagal menghapus data convert.');
        }
    }

    // Method create() dan edit() tidak dipakai
    public function create() { abort(404); }
    public function edit(Convert $convert) { abort(404); }
}
