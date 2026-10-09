<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedInteger('milestone')->default(0);
            $table->json('networks')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'kind', 'milestone']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_promotions');
    }
};
