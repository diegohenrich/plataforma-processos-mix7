<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_module_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('key', 60);
            $table->string('label', 120);
            $table->string('description', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::table('demands', function (Blueprint $table): void {
            $table->string('module_label', 120)->nullable()->after('module_version');
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropColumn('module_label');
        });
        Schema::dropIfExists('demand_module_definitions');
    }
};
