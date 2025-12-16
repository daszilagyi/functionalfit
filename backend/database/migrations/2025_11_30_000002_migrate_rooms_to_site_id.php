<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrate rooms.site enum to rooms.site_id foreign key
     * 1. Create default sites (SASAD, TB, ÚJBUDA)
     * 2. Add site_id column to rooms
     * 3. Migrate data from site enum to site_id
     * 4. Drop old site enum column
     */
    public function up(): void
    {
        // Step 1: Insert default sites (if not already exist)
        $sites = [
            [
                'name' => 'SASAD',
                'slug' => 'sasad',
                'address' => 'Sasad út 181',
                'city' => 'Budapest',
                'postal_code' => '1112',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'TB',
                'slug' => 'tb',
                'address' => 'Tatabánya',
                'city' => 'Tatabánya',
                'postal_code' => '2800',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ÚJBUDA',
                'slug' => 'ujbuda',
                'address' => 'Újbuda',
                'city' => 'Budapest',
                'postal_code' => '1117',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($sites as $site) {
            // Only insert if doesn't exist
            if (!DB::table('sites')->where('slug', $site['slug'])->exists()) {
                DB::table('sites')->insert($site);
            }
        }

        // Step 2: Add site_id column to rooms (nullable temporarily) - check if exists first
        if (!Schema::hasColumn('rooms', 'site_id')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unsignedBigInteger('site_id')->nullable()->after('id');
                $table->foreign('site_id')->references('id')->on('sites')->onDelete('restrict');
                $table->index('site_id');
            });
        }

        // Step 3: Migrate data - Map enum to site_id
        $siteMap = DB::table('sites')->pluck('id', 'name')->toArray();

        DB::table('rooms')->whereNotNull('site')->orderBy('id')->each(function ($room) use ($siteMap) {
            $siteName = $room->site;
            if (isset($siteMap[$siteName])) {
                DB::table('rooms')
                    ->where('id', $room->id)
                    ->update(['site_id' => $siteMap[$siteName]]);
            }
        });

        // Step 4: Make site_id NOT NULL
        // Note: Skipping dropColumn('site') due to SQLite limitations with indexes
        // The old 'site' column will remain but won't be used (cleaned up in production MySQL)
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back site enum column
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('site', ['SASAD', 'TB', 'ÚJBUDA'])->nullable()->after('id');
        });

        // Step 2: Migrate data back from site_id to site enum
        DB::table('rooms')->whereNotNull('site_id')->orderBy('id')->each(function ($room) {
            $site = DB::table('sites')->where('id', $room->site_id)->first();
            if ($site) {
                DB::table('rooms')
                    ->where('id', $room->id)
                    ->update(['site' => $site->name]);
            }
        });

        // Step 3: Make site NOT NULL and drop site_id
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('site', ['SASAD', 'TB', 'ÚJBUDA'])->nullable(false)->change();
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });

        // Step 4: Delete sites
        DB::table('sites')->truncate();
    }
};
