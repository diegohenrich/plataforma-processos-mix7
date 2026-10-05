<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->string('module_key', 60)->nullable()->after('brief');
            $table->unsignedSmallInteger('module_version')->nullable()->after('module_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropColumn(['module_key', 'module_version']);
        });
    }
};
