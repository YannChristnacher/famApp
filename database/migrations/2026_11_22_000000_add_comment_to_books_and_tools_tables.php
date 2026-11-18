<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free comment of the owner on a book or a tool (F15), shown as plain
     * text on its page; null without comment.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('isbn');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('comment');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
