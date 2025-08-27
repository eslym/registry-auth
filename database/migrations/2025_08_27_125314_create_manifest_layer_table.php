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
        Schema::create('manifest_layer', function (Blueprint $table) {
            $table->string('manifest_digest')
                ->references('digest')
                ->on('manifests')
                ->onDelete('cascade');
            $table->string('blob_digest')
                ->references('digest')
                ->on('blobs')
                ->onDelete('no action');
            $table->unsignedInteger('layer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_layer');
    }
};
