<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('original_first_name')->nullable()->after('last_name');
            $table->string('original_last_name')->nullable()->after('original_first_name');
            $table->string('original_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('original_first_name');
            $table->dropColumn('original_last_name');
            $table->dropColumn('original_email');
        });
    }
};
