<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            if (!Schema::hasColumn('otp', 'password')) {
                return;
            }

            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE otp ALTER COLUMN password TYPE TEXT');
            } else {
                DB::statement('ALTER TABLE otp MODIFY password TEXT NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            if (!Schema::hasColumn('otp', 'password')) {
                return;
            }

            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE otp ALTER COLUMN password TYPE VARCHAR(1000)');
            } else {
                DB::statement('ALTER TABLE otp MODIFY password VARCHAR(1000) NULL');
            }
        });
    }
};
