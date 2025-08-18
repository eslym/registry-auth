<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable()->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->boolean('is_admin')->default(false);
            $table->timestamp('password_expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        $username = ($_ENV['ADMIN_INIT_USERNAME'] ?? '') ?: 'admin';
        $password = ($_ENV['ADMIN_INIT_PASSWORD'] ?? '') ?: Str::random(20);

        DB::table('users')
            ->insert([
                [
                    'username' => null,
                    'password' => null,
                    'is_admin' => false,
                    'password_expired_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'username' => $username,
                    'password' => Hash::make($password),
                    'is_admin' => true,
                    'password_expired_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);

        Log::info("Admin user created", [
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
