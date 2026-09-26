<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_delivery_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('outcome', 24);
            $table->string('evidence_url', 2048)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'demand_id', 'created_at'], 'delivery_evidence_demand_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_delivery_evidences');
    }
};
