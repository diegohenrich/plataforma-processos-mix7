<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path', 1024)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
            $table->index(['organization_id', 'demand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_attachments');
    }
};
