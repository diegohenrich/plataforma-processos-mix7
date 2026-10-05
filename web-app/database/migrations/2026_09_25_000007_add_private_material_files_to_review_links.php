<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_review_links', function (Blueprint $table): void {
            $table->string('material_url', 2048)->nullable()->change();
            $table->string('material_file_path', 1024)->nullable()->after('material_url');
            $table->string('material_file_name', 255)->nullable()->after('material_file_path');
            $table->string('material_mime', 120)->nullable()->after('material_file_name');
            $table->unsignedBigInteger('material_file_size')->nullable()->after('material_mime');
        });
    }

    public function down(): void
    {
        if (DB::table('demand_review_links')->whereNotNull('material_file_path')->exists()) {
            throw new RuntimeException('Remove version links with private files before rolling back migration 000007.');
        }

        Schema::table('demand_review_links', function (Blueprint $table): void {
            $table->dropColumn(['material_file_path', 'material_file_name', 'material_mime', 'material_file_size']);
            $table->string('material_url', 2048)->nullable(false)->change();
        });
    }
};
