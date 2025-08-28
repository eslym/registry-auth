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
        Schema::create('repository_manifest', function (Blueprint $table) {
            $table->string('repository')
                ->references('name')
                ->on('repositories')
                ->onDelete('cascade');
            $table->string('digest')
                ->references('digest')
                ->on('manifests')
                ->onDelete('cascade');

            $table->primary(['repository', 'digest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_manifest');
    }
};
