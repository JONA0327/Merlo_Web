<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // role and email_verified_at aren't mass-assignable (by design, so
        // a stray `User::create($request->all())` elsewhere could never let
        // someone grant themselves admin), so they're set via forceFill here.
        $admin = User::firstOrNew(['email' => 'soportemerlotransportes@gmail.com']);

        $admin->name = 'Soporte Merlo Transportes';

        if (! $admin->exists) {
            $admin->password = Hash::make(Str::random(40));
        }

        $admin->forceFill([
            'role' => User::ROLE_SUPERADMIN,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
