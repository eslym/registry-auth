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
        Schema::create('manifest_manifest', function (Blueprint $table) {
            $table->string('parent_digest')
                ->references('digest')
                ->on('manifests')
                ->onDelete('cascade');
            $table->string('child_digest')
                ->references('digest')
                ->on('manifests')
                ->onDelete('cascade');

            $table->string('os')->nullable();
            $table->string('arch')->nullable();

            $table->string('platform')
                ->storedAs("CONCAT(COALESCE(`os`, 'unknown'), '/', COALESCE(`arch`, 'unknown'))");

            $table->unsignedInteger('manifest_index');

            $table->primary(['parent_digest', 'child_digest']);
            $table->index(['arch', 'os']);
            $table->index("platform");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_manifest');
    }
};
