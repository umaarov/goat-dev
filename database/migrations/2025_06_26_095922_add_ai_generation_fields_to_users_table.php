<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('ai_generations_monthly_count')->default(0)->after('remember_token');
            $table->integer('ai_generations_daily_count')->default(0)->after('ai_generations_monthly_count');
            $table->date('last_ai_generation_date')->nullable()->after('ai_generations_daily_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ai_generations_monthly_count',
                'ai_generations_daily_count',
                'last_ai_generation_date',
            ]);
        });
    }
};
