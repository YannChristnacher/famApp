<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared by the whole family; never deleted with a tool.
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Brand::normalizeName(): compared byte for byte on every engine,
            // so that accents count ("Leroy" and "Leröy" are two brands).
            $normalizedName = $table->string('normalized_name', 100);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $normalizedName->collation('utf8mb4_bin');
            }
            $table->timestamps();

            $table->unique('normalized_name');
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        // Deleting a shelf or a tool removes its links, never the other side.
        Schema::create('tool_tool_shelf', function (Blueprint $table) {
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_shelf_id')->constrained()->cascadeOnDelete();

            $table->primary(['tool_id', 'tool_shelf_id']);
        });

        // The files on the public disk are removed by ToolService, not by the cascade.
        Schema::create('tool_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['tool_id', 'position']);
        });

        Schema::create('tool_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['tool_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_accessories');
        Schema::dropIfExists('tool_photos');
        Schema::dropIfExists('tool_tool_shelf');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('brands');
    }
};
