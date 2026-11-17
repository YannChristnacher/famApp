<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            // Normalized (digits, final X): the same ISBN may appear several times.
            $table->string('isbn', 13)->nullable();
            $table->string('cover_path')->nullable();
            // Raw answer of Google Books (F07); never filled from the form.
            $table->json('google_books_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'title']);
        });

        // Deleting a bookshelf or a book removes its links, never the other side.
        Schema::create('book_bookshelf', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bookshelf_id')->constrained()->cascadeOnDelete();

            $table->primary(['book_id', 'bookshelf_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_bookshelf');
        Schema::dropIfExists('books');
    }
};
