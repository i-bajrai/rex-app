<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('address')->unique();
            $table->string('address_domain')
                ->storedAs('substr(address, instr(address, \'@\') + 1)')
                ->index();
            $table->timestamps();
        });
    }
};
