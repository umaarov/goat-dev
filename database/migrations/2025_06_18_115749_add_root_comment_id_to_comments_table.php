<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('root_comment_id')->nullable()->after('parent_id');

            $table->index('root_comment_id');

            $table->foreign('root_comment_id')
                ->references('id')
                ->on('comments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['root_comment_id']);
            $table->dropColumn('root_comment_id');
        });
    }
};
