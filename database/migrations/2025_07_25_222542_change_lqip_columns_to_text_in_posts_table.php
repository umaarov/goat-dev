<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('option_one_image_lqip')->nullable()->change();
            $table->text('option_two_image_lqip')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('option_one_image_lqip', 255)->change();
            $table->string('option_two_image_lqip', 255)->change();
        });
    }
};
