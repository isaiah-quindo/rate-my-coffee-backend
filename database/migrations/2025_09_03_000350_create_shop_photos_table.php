<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS shop_photos (
                id           BIGSERIAL PRIMARY KEY,
                shop_id      BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
                url          TEXT NOT NULL,
                caption      TEXT,
                is_cover     BOOLEAN NOT NULL DEFAULT FALSE,
                sort_order   INT NOT NULL DEFAULT 0,
                created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );

            CREATE INDEX IF NOT EXISTS shop_photos_shop_idx ON shop_photos(shop_id, is_cover, sort_order);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS shop_photos;
        SQL);
    }
};
