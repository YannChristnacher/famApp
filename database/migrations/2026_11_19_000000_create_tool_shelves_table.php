<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // ToolShelf::normalizeName(): compared byte for byte on every
            // engine, so that accents count ("Café" and "Cafe" are two names).
            $normalizedName = $table->string('normalized_name', 100);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $normalizedName->collation('utf8mb4_bin');
            }
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['user_id', 'normalized_name']);
            $table->unique(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_shelves');
    }
};
