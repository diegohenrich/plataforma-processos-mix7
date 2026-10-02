<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->string('ai_summary', 280)->nullable()->after('brief');
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropColumn('ai_summary');
        });
    }
};
