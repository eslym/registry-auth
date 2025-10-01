<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repository_tags', function (Blueprint $table) {
            $table->timestamp('flagged_prune_at')->nullable()->after('manifest_digest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repository_tags', function (Blueprint $table) {
            $table->dropColumn('flagged_prune_at');
        });
    }
};
