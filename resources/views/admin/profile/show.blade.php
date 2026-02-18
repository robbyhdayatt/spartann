@extends('adminlte::page')

@section('title', 'Profil Saya')

@section('content_header')
    <h1>Profil Pengguna</h1>
@stop

@section('content')
<div class="row">
    {{-- KOLOM KIRI: KARTU PROFIL --}}
    <div class="col-md-3">
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    {{-- Avatar Otomatis Berdasarkan Inisial Nama --}}
                    <img class="profile-user-img img-fluid img-circle"
                         src="https://ui-avatars.com/api/?name={{ urlencode($user->nama) }}&background=random&color=fff&size=128"
                         alt="User profile picture">
                </div>

                <h3 class="profile-username text-center">{{ $user->nama }}</h3>
                <p class="text-muted text-center">{{ $user->jabatan->nama_jabatan ?? 'Staff' }}</p>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Status</b> <a class="float-right badge badge-success">Aktif</a>
                    </li>
                    <li class="list-group-item">
                        <b>Lokasi</b> <a class="float-right text-muted">{{ $user->lokasi->singkatan ?? '-' }}</a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Info Tambahan --}}
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Tentang Saya</h3>
            </div>
            <div class="card-body">
                <strong><i class="fas fa-id-card mr-1"></i> NIK</strong>
                <p class="text-muted">{{ $user->nik }}</p>
                <hr>
                <strong><i class="fas fa-map-marker-alt mr-1"></i> Lokasi Kerja</strong>
                <p class="text-muted">{{ $user->lokasi->nama_lokasi ?? 'Kantor Pusat' }}</p>
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN: TABS (INFO & EDIT) --}}
    <div class="col-md-9">
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills">
                    <li class="nav-item"><a class="nav-link {{ session('active_tab') ? '' : 'active' }}" href="#biodata" data-toggle="tab">Biodata Lengkap</a></li>
                    <li class="nav-item"><a class="nav-link {{ session('active_tab') == 'password' ? 'active' : '' }}" href="#settings" data-toggle="tab">Edit Profil & Password</a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    
                    {{-- TAB 1: BIODATA (Read Only) --}}
                    <div class="{{ session('active_tab') ? '' : 'active' }} tab-pane" id="biodata">
                        <form class="form-horizontal">
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">Nama Lengkap</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" value="{{ $user->nama }}" readonly>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">Username</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" value="{{ $user->username }}" readonly>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label">Jabatan</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" value="{{ $user->jabatan->nama_jabatan ?? '-' }}" readonly>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- TAB 2: EDIT SETTINGS --}}
                    <div class="{{ session('active_tab') == 'password' ? 'active' : '' }} tab-pane" id="settings">
                        
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                <i class="icon fas fa-check"></i> {{ session('success') }}
                            </div>
                        @endif

                        <form class="form-horizontal" action="{{ route('admin.profile.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <h5 class="text-primary mb-3"><i class="fas fa-user-edit"></i> Ubah Informasi Dasar</h5>
                            
                            <div class="form-group row">
                                <label for="inputName" class="col-sm-3 col-form-label">Nama Lengkap</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control @error('nama') is-invalid @enderror" id="inputName" name="nama" value="{{ old('nama', $user->nama) }}">
                                    @error('nama') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label for="inputUserName" class="col-sm-3 col-form-label">Username</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="inputUserName" name="username" value="{{ old('username', $user->username) }}">
                                    @error('username') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <hr>
                            <h5 class="text-danger mb-3"><i class="fas fa-lock"></i> Ganti Password</h5>
                            <p class="text-muted text-sm font-italic">Kosongkan jika tidak ingin mengganti password.</p>

                            <div class="form-group row">
                                <label for="current_password" class="col-sm-3 col-form-label">Password Lama</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" placeholder="Password saat ini">
                                    @error('current_password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="new_password" class="col-sm-3 col-form-label">Password Baru</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control @error('new_password') is-invalid @enderror" id="new_password" name="new_password" placeholder="Password baru (Min. 8 karakter)">
                                    @error('new_password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="new_password_confirmation" class="col-sm-3 col-form-label">Konfirmasi Password</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" placeholder="Ulangi password baru">
                                </div>
                            </div>

                            <div class="form-group row mt-4">
                                <div class="offset-sm-3 col-sm-9">
                                    <button type="submit" class="btn btn-danger">Simpan Perubahan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop