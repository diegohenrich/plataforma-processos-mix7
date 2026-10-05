<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->foreignId('responsible_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->text('materials_location')->nullable()->after('brief');
            $table->text('access_instructions')->nullable()->after('materials_location');
        });

        DB::table('demands')->update(['brief_author_id' => DB::raw('created_by')]);

        DB::table('demands')->select(['id'])->orderBy('id')->chunkById(200, function ($demands): void {
            foreach ($demands as $demand) {
                $professionalId = DB::table('demand_tasks')->where('demand_id', $demand->id)->orderBy('id')->value('assigned_to');
                if ($professionalId) {
                    DB::table('demands')->where('id', $demand->id)->whereNull('responsible_user_id')->update(['responsible_user_id' => $professionalId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn(['materials_location', 'access_instructions']);
        });
    }
};
