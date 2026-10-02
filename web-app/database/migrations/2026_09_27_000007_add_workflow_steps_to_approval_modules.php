<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_module_definitions', function (Blueprint $table): void {
            $table->json('workflow_steps')->nullable()->after('fields');
        });

        Schema::create('demand_module_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 80);
            $table->unsignedSmallInteger('position');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['demand_id', 'key']);
            $table->index(['organization_id', 'demand_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_module_steps');
        Schema::table('demand_module_definitions', function (Blueprint $table): void {
            $table->dropColumn('workflow_steps');
        });
    }
};
