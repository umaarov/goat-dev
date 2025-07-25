<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('option_one_image_lqip')->nullable()->after('option_one_image');
            $table->string('option_two_image_lqip')->nullable()->after('option_two_image');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['option_one_image_lqip', 'option_two_image_lqip']);
        });
    }
};
