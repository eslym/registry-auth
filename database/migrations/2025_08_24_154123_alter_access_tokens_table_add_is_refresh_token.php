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
        Schema::table('access_tokens', function (Blueprint $table) {
            $table->boolean('is_refresh_token')
                ->after('token')
                ->default(false);
            $table->string('last_used_ip')->nullable()
                ->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_tokens', function (Blueprint $table) {
            $table->dropColumn(['is_refresh_token', 'last_used_ip']);
        });
    }
};
