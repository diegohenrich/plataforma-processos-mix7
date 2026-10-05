<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_review_responses', function (Blueprint $table): void {
            $table->string('anchor_type', 24)->nullable()->after('comment');
            $table->json('anchor_data')->nullable()->after('anchor_type');
        });
    }

    public function down(): void
    {
        Schema::table('demand_review_responses', function (Blueprint $table): void {
            $table->dropColumn(['anchor_type', 'anchor_data']);
        });
    }
};
