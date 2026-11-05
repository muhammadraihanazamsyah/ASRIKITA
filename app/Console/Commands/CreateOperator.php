<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateOperator extends Command
{
    protected $signature = 'asrikita:create-operator';

    protected $description = 'Buat akun admin/petugas aktif dengan kata sandi tersembunyi.';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Nama operator')),
            'email' => Str::lower(trim((string) $this->ask('Email operator'))),
            'role' => $this->choice('Peran', ['staff', 'admin'], 0),
            'password' => $this->secret('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)'),
            'password_confirmation' => $this->secret('Ulangi kata sandi'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', 'in:admin,staff'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->status = 'active';
        $user->password = $data['password'];
        $user->save();
        $this->info('Akun operator aktif berhasil dibuat. Kata sandi tidak ditampilkan atau disimpan dalam teks biasa.');

        return self::SUCCESS;
    }
}
