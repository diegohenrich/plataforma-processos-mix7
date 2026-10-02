<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_module_definitions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('config_version')->default(1)->after('description');
            $table->json('fields')->nullable()->after('config_version');
        });

        Schema::table('demands', function (Blueprint $table): void {
            $table->json('module_fields_schema')->nullable()->after('module_label');
            $table->json('module_fields_data')->nullable()->after('module_fields_schema');
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropColumn(['module_fields_schema', 'module_fields_data']);
        });

        Schema::table('demand_module_definitions', function (Blueprint $table): void {
            $table->dropColumn(['config_version', 'fields']);
        });
    }
};
