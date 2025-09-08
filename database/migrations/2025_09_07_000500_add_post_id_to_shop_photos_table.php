<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE shop_photos
            ADD COLUMN IF NOT EXISTS post_id BIGINT NULL REFERENCES posts(id) ON DELETE CASCADE;

            CREATE INDEX IF NOT EXISTS shop_photos_post_idx ON shop_photos(post_id, sort_order);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS shop_photos_post_idx;
            ALTER TABLE shop_photos DROP COLUMN IF EXISTS post_id;
        SQL);
    }
};
