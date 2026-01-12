<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'github_id')) {
                $table->string('github_id')->nullable()->after('telegram_id');
            }
            if (!Schema::hasColumn('users', 'x_username')) {
                $table->string('x_username')->nullable()->after('x_id');
            }
            if (!Schema::hasColumn('users', 'telegram_username')) {
                $table->string('telegram_username')->nullable()->after('telegram_id');
            }
            if (!Schema::hasColumn('users', 'is_developer')) {
                $table->boolean('is_developer')->default(false)->after('github_id');
            }
            if (!Schema::hasColumn('users', 'header_background')) {
                $table->string('header_background')->nullable()->after('profile_picture');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_id', 'x_username', 'telegram_username', 'is_developer', 'header_background']);
        });
    }
};
