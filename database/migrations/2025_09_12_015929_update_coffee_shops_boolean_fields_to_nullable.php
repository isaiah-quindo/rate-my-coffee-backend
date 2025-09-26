<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- Update Commercial hints fields to allow NULL
            ALTER TABLE coffee_shops 
            ALTER COLUMN accepts_gcash DROP NOT NULL,
            ALTER COLUMN accepts_gcash DROP DEFAULT,
            ALTER COLUMN accepts_cards DROP NOT NULL,
            ALTER COLUMN accepts_cards DROP DEFAULT;

            -- Update Amenities fields to allow NULL
            ALTER TABLE coffee_shops 
            ALTER COLUMN has_wifi DROP NOT NULL,
            ALTER COLUMN has_wifi DROP DEFAULT,
            ALTER COLUMN has_outlets DROP NOT NULL,
            ALTER COLUMN has_outlets DROP DEFAULT,
            ALTER COLUMN outdoor_seating DROP NOT NULL,
            ALTER COLUMN outdoor_seating DROP DEFAULT,
            ALTER COLUMN parking_available DROP NOT NULL,
            ALTER COLUMN parking_available DROP DEFAULT,
            ALTER COLUMN wheelchair_accessible DROP NOT NULL,
            ALTER COLUMN wheelchair_accessible DROP DEFAULT,
            ALTER COLUMN pet_friendly DROP NOT NULL,
            ALTER COLUMN pet_friendly DROP DEFAULT,
            ALTER COLUMN vegan_options DROP NOT NULL,
            ALTER COLUMN vegan_options DROP DEFAULT,
            ALTER COLUMN manual_brew DROP NOT NULL,
            ALTER COLUMN manual_brew DROP DEFAULT,
            ALTER COLUMN decaf_available DROP NOT NULL,
            ALTER COLUMN decaf_available DROP DEFAULT;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            -- Revert Commercial hints fields to NOT NULL with defaults
            ALTER TABLE coffee_shops 
            ALTER COLUMN accepts_gcash SET NOT NULL,
            ALTER COLUMN accepts_gcash SET DEFAULT TRUE,
            ALTER COLUMN accepts_cards SET NOT NULL,
            ALTER COLUMN accepts_cards SET DEFAULT TRUE;

            -- Revert Amenities fields to NOT NULL with defaults
            ALTER TABLE coffee_shops 
            ALTER COLUMN has_wifi SET NOT NULL,
            ALTER COLUMN has_wifi SET DEFAULT TRUE,
            ALTER COLUMN has_outlets SET NOT NULL,
            ALTER COLUMN has_outlets SET DEFAULT TRUE,
            ALTER COLUMN outdoor_seating SET NOT NULL,
            ALTER COLUMN outdoor_seating SET DEFAULT FALSE,
            ALTER COLUMN parking_available SET NOT NULL,
            ALTER COLUMN parking_available SET DEFAULT FALSE,
            ALTER COLUMN wheelchair_accessible SET NOT NULL,
            ALTER COLUMN wheelchair_accessible SET DEFAULT FALSE,
            ALTER COLUMN pet_friendly SET NOT NULL,
            ALTER COLUMN pet_friendly SET DEFAULT FALSE,
            ALTER COLUMN vegan_options SET NOT NULL,
            ALTER COLUMN vegan_options SET DEFAULT FALSE,
            ALTER COLUMN manual_brew SET NOT NULL,
            ALTER COLUMN manual_brew SET DEFAULT FALSE,
            ALTER COLUMN decaf_available SET NOT NULL,
            ALTER COLUMN decaf_available SET DEFAULT FALSE;
            SQL);
    }
};
