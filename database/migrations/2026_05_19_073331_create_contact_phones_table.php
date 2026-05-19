<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->index()->constrained()->cascadeOnDelete();
            $table->string('e164', 20)->unique();
            $table->timestamps();
        });
    }
};
