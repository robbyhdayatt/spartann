<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convert;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ConvertController extends Controller
{
    private $convertsMainTable = 'converts_main';

    public function __construct()
    {
        $this->middleware('can:manage-converts');
    }

    public function index()
    {
        try {
            $converts = Convert::orderBy('nama_job')->get();
            $barangs = Barang::where('is_active', true)->orderBy('part_name')->get();
            return view('admin.converts.index', compact('converts', 'barangs'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memuat data: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_job' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'keterangan' => 'nullable|string',
                'part_code' => [
                    'required',
                    'string',
                    'exists:barangs,part_code',
                    Rule::unique($this->convertsMainTable)->where(function ($query) use ($request) {
                        return $query->where('nama_job', $request->nama_job);
                    }),
                ],
            ], [
                'part_code.unique' => 'Part ini sudah terdaftar untuk nama job tersebut.',
                'part_code.exists' => 'Kode Part tidak ditemukan di Master Barang.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::table($this->convertsMainTable)->insert([
                'nama_job' => $request->nama_job,
                'quantity' => $request->quantity,
                'keterangan' => $request->keterangan,
                'part_code' => $request->part_code,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => 'Data convert berhasil ditambahkan.']);

        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'SQL Error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'System Error: ' . $e->getMessage()], 500);
        }
    }

    public function getEditData($id)
    {
        try {
            $convert = Convert::findOrFail($id);
            return response()->json($convert);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Data tidak ditemukan.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_job' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'keterangan' => 'nullable|string',
                'part_code' => [
                    'required',
                    'string',
                    'exists:barangs,part_code',
                    Rule::unique($this->convertsMainTable)->where(function ($query) use ($request) {
                        return $query->where('nama_job', $request->nama_job);
                    })->ignore($id),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::table($this->convertsMainTable)->where('id', $id)->update([
                'nama_job' => $request->nama_job,
                'quantity' => $request->quantity,
                'keterangan' => $request->keterangan,
                'part_code' => $request->part_code,
                'updated_at' => now()
            ]);

            return response()->json(['success' => 'Data convert berhasil diperbarui.']);

        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'SQL Error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'System Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::table($this->convertsMainTable)->where('id', $id)->delete();
            return redirect()->route('admin.converts.index')->with('success', 'Data convert berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('admin.converts.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    public function create() { abort(404); }
    public function edit($id) { abort(404); }
}
