<?php

use App\Enums\AccountRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->text('message')->nullable();
            $table->string('status')->default(AccountRequestStatus::Pending->value)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
