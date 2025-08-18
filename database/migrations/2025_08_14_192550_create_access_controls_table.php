<?php

use App\Enums\AccessLevel;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('access_controls', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('repository');
            $table->string('access_level')->default(AccessLevel::PULL_ONLY);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'repository'], 'unique_access_control');
        });

        DB::table('access_controls')->insert([
            [
                'owner_type' => User::class,
                'owner_id' => 1,
                'repository' => 'public/**',
                'access_level' => AccessLevel::PULL_ONLY,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'owner_type' => User::class,
                'owner_id' => 2,
                'repository' => '**',
                'access_level' => AccessLevel::PULL_PUSH,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_controls');
    }
};
