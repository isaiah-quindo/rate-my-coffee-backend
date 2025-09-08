<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shop_hours (
                shop_id        BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
                day_of_week    SMALLINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
                open_time      TIME,
                close_time     TIME,
                is_24h         BOOLEAN NOT NULL DEFAULT FALSE,
                notes          TEXT,
                PRIMARY KEY (shop_id, day_of_week, open_time)
            );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS shop_hours;
        SQL);
    }
};
