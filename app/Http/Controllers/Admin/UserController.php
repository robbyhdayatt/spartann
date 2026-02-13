<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Jabatan;
use App\Models\Lokasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index()
    {
        $users = User::with(['jabatan', 'lokasi'])->latest()->get();
        $jabatans = Jabatan::where('is_active', true)->orderBy('nama_jabatan')->get();
        $lokasi = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();

        return view('admin.users.index', compact('users', 'jabatans', 'lokasi'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nik' => 'required|string|max:50|unique:users',
            'username' => 'required|string|max:100|unique:users',
            'jabatan_id' => 'required|exists:jabatans,id',
            'lokasi_id' => 'nullable|exists:lokasi,id',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'nama' => $validated['nama'],
            'nik' => $validated['nik'],
            'username' => $validated['username'],
            'jabatan_id' => $validated['jabatan_id'],
            'lokasi_id' => $validated['lokasi_id'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nik' => 'required|string|max:50|unique:users,nik,' . $user->id,
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'jabatan_id' => 'required|exists:jabatans,id',
            'lokasi_id' => 'nullable|exists:lokasi,id',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'required|boolean',
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        unset($validated['password']);
        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui!');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }

    protected function resourceAbilityMap()
    {
        return [
            'index' => 'manage-users',
            'create' => 'manage-users',
            'store' => 'manage-users',
            'edit' => 'manage-users',
            'update' => 'manage-users',
            'destroy' => 'manage-users',
        ];
    }
}
