<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->unsignedTinyInteger('deadline_score')->nullable()->after('reviewer_weight');
            $table->unsignedTinyInteger('quality_score')->nullable()->after('deadline_score');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropColumn(['deadline_score', 'quality_score']);
        });
    }
};
