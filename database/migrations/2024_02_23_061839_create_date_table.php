<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date', static function (Blueprint $table) {
            $table->id();
            $table->timestamp('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date');
    }
};
