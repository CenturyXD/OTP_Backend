<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            $table->string('oauth_client_id')->nullable()->after('refresh_token');
            $table->text('oauth_client_secret')->nullable()->after('oauth_client_id');
            $table->string('oauth_tenant_id')->nullable()->after('oauth_client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            $table->dropColumn([
                'oauth_client_id',
                'oauth_client_secret',
                'oauth_tenant_id',
            ]);
        });
    }
};
