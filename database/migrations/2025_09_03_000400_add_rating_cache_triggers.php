<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- Function to recalc rating cache for a shop
            CREATE OR REPLACE FUNCTION recalc_shop_rating_cache(p_shop_id BIGINT)
            RETURNS VOID AS $$
            DECLARE
                v_count INTEGER;
                v_avg NUMERIC(3,2);
            BEGIN
                SELECT COUNT(*)::INT, ROUND(AVG(overall_score)::numeric, 2)
                INTO v_count, v_avg
                FROM posts
                WHERE shop_id = p_shop_id AND status = 'published' AND overall_score IS NOT NULL;

                UPDATE coffee_shops
                SET rating_count_cache = COALESCE(v_count, 0),
                    rating_overall_cache = v_avg
                WHERE id = p_shop_id;
            END;
            $$ LANGUAGE plpgsql;

            -- Trigger function to call recalc on posts changes
            CREATE OR REPLACE FUNCTION posts_after_change_recalc_shop()
            RETURNS TRIGGER AS $$
            BEGIN
                -- handle insert/update/delete and status changes
                IF TG_OP = 'INSERT' THEN
                    PERFORM recalc_shop_rating_cache(NEW.shop_id);
                    RETURN NEW;
                ELSIF TG_OP = 'UPDATE' THEN
                    IF NEW.shop_id <> OLD.shop_id THEN
                        PERFORM recalc_shop_rating_cache(OLD.shop_id);
                        PERFORM recalc_shop_rating_cache(NEW.shop_id);
                    ELSE
                        PERFORM recalc_shop_rating_cache(NEW.shop_id);
                    END IF;
                    RETURN NEW;
                ELSIF TG_OP = 'DELETE' THEN
                    PERFORM recalc_shop_rating_cache(OLD.shop_id);
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS posts_after_change_recalc_shop_trg ON posts;
            CREATE TRIGGER posts_after_change_recalc_shop_trg
            AFTER INSERT OR UPDATE OR DELETE ON posts
            FOR EACH ROW EXECUTE FUNCTION posts_after_change_recalc_shop();

            -- Backfill existing shops
            DO $$
            DECLARE r RECORD;
            BEGIN
                FOR r IN SELECT id FROM coffee_shops LOOP
                    PERFORM recalc_shop_rating_cache(r.id);
                END LOOP;
            END$$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS posts_after_change_recalc_shop_trg ON posts;
            DROP FUNCTION IF EXISTS posts_after_change_recalc_shop();
            DROP FUNCTION IF EXISTS recalc_shop_rating_cache(BIGINT);
        SQL);
    }
};
