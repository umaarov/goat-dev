<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('question');
            $table->string('option_one_title');
            $table->string('option_one_image')->nullable();
            $table->string('option_two_title');
            $table->string('option_two_image')->nullable();
            $table->integer('option_one_votes')->default(0);
            $table->integer('option_two_votes')->default(0);
            $table->integer('total_votes')->default(0);
            $table->integer('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
