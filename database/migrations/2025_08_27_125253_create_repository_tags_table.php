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
        Schema::create('repository_tags', function (Blueprint $table) {
            $table->string('repository')
                ->references('name')
                ->on('repositories')
                ->onDelete('cascade');
            $table->string('tag');
            $table->string('reference')
                ->storedAs("CONCAT(`repository`, ':', `tag`)")
                ->unique();
            $table->string('manifest_digest')
                ->references('digest')
                ->on('manifests')
                ->onDelete('cascade');
            $table->timestamps();

            $table->primary(['repository', 'tag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_tags');
    }
};
