<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('theme[primary]')->nullable()->after('email');
            $table->string('theme[primary-dark]')->nullable()->after('email');
            $table->string('theme[accent]')->nullable()->after('email');
            $table->string('theme[secondary]')->nullable()->after('email');
            $table->string('theme[gradient]')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn('theme[primary]');
        });
    }
};
