<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `parent_id` column to `comments`.
 *
 * This column is used by the threaded-reply feature (Comment::parent / replies)
 * and is written by both the web CommentController and the mobile API, but no
 * prior migration created it — it currently exists in production only via a
 * manual/out-of-band change. The hasColumn() guard makes this migration a
 * no-op where the column already exists (production) while repairing fresh
 * environments (CI, new setups) so comments work there too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (! Schema::hasColumn('comments', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('post_id');
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (Schema::hasColumn('comments', 'parent_id')) {
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
