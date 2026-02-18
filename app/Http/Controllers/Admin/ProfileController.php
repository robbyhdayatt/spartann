<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Menampilkan halaman profil.
     */
    public function show()
    {
        $user = Auth::user();
        return view('admin.profile.show', compact('user'));
    }

    /**
     * Memproses update profil dan password.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // 1. Validasi Input Umum
        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('users')->ignore($user->id) // Abaikan validasi unik untuk user ini sendiri
            ],
        ]);

        // 2. Cek apakah user ingin ganti password
        if ($request->filled('current_password') || $request->filled('new_password')) {
            $request->validate([
                'current_password' => 'required',
                'new_password'     => 'required|string|min:8|confirmed', // field konfirmasi harus bernama new_password_confirmation
            ]);

            // Cek Password Lama
            if (!Hash::check($request->current_password, $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'Password lama yang Anda masukkan salah.'])
                    ->withInput()
                    ->with('active_tab', 'password'); // Agar tab password tetap terbuka saat error
            }

            // Update Password Baru
            $user->password = Hash::make($request->new_password);
        }

        // 3. Update Data Profil
        $user->nama = $request->nama;
        $user->username = $request->username;
        $user->save();

        return redirect()->route('admin.profile.show')->with('success', 'Profil berhasil diperbarui.');
    }
}