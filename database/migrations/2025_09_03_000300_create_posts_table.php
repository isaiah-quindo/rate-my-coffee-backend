<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'post_status') THEN
                    CREATE TYPE post_status AS ENUM ('draft','published','flagged','removed');
                END IF;
            END$$;

            CREATE TABLE IF NOT EXISTS posts (
                id                  BIGSERIAL PRIMARY KEY,
                shop_id             BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
                author_user_id      BIGINT REFERENCES users(id) ON DELETE SET NULL,
                is_anonymous        BOOLEAN NOT NULL DEFAULT FALSE,

                body                TEXT,

                ratings             JSONB NOT NULL,

                visited_at          DATE,
                spend_php           NUMERIC(10,2) CHECK (spend_php >= 0),
                ordered_items       TEXT[] DEFAULT '{}'::TEXT[],

                taste_profile       JSONB,
                seat_context        TEXT,
                internet_speed_mbps NUMERIC(6,2) CHECK (internet_speed_mbps >= 0),

                status              post_status NOT NULL DEFAULT 'published',
                flagged_count       INTEGER NOT NULL DEFAULT 0,
                admin_notes         TEXT,
                deleted_at          TIMESTAMPTZ,

                ip_hash             BYTEA,
                user_agent          TEXT,

                created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),

                overall_score       NUMERIC(3,2) GENERATED ALWAYS AS (
                    (
                      COALESCE((ratings->>'coffee_quality')::NUMERIC, 0) +
                      COALESCE((ratings->>'vibe')::NUMERIC, 0) +
                      COALESCE((ratings->>'service')::NUMERIC, 0) +
                      COALESCE((ratings->>'value')::NUMERIC, 0) +
                      COALESCE((ratings->>'wifi')::NUMERIC, 0) +
                      COALESCE((ratings->>'noise')::NUMERIC, 0) +
                      COALESCE((ratings->>'seating')::NUMERIC, 0) +
                      COALESCE((ratings->>'outlets')::NUMERIC, 0) +
                      COALESCE((ratings->>'cleanliness')::NUMERIC, 0) +
                      COALESCE((ratings->>'food')::NUMERIC, 0) +
                      COALESCE((ratings->>'location_convenience')::NUMERIC, 0) +
                      COALESCE((ratings->>'consistency')::NUMERIC, 0)
                    )
                    /
                    NULLIF(
                      (
                        (CASE WHEN ratings ? 'coffee_quality' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'vibe' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'service' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'value' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'wifi' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'noise' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'seating' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'outlets' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'cleanliness' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'food' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'location_convenience' THEN 1 ELSE 0 END) +
                        (CASE WHEN ratings ? 'consistency' THEN 1 ELSE 0 END)
                      ), 0)
                ) STORED
            );

            CREATE INDEX IF NOT EXISTS posts_shop_idx   ON posts(shop_id);
            CREATE INDEX IF NOT EXISTS posts_author_idx ON posts(author_user_id) WHERE author_user_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS posts_status_idx ON posts(status);
            CREATE INDEX IF NOT EXISTS posts_created_idx ON posts(created_at DESC);
            CREATE INDEX IF NOT EXISTS posts_ratings_gin ON posts USING GIN (ratings jsonb_path_ops);

            CREATE UNIQUE INDEX IF NOT EXISTS posts_unique_user_shop ON posts(shop_id, author_user_id)
                WHERE author_user_id IS NOT NULL AND status IN ('published','draft');

            CREATE OR REPLACE FUNCTION posts_validate_ratings()
            RETURNS TRIGGER AS $$
            DECLARE
              k TEXT;
              v JSONB;
              n NUMERIC;
            BEGIN
              IF NEW.ratings IS NULL OR jsonb_typeof(NEW.ratings) <> 'object' THEN
                RAISE EXCEPTION 'ratings must be a JSON object';
              END IF;

              FOR k, v IN SELECT key, value FROM jsonb_each(NEW.ratings)
              LOOP
                IF jsonb_typeof(v) <> 'number' THEN
                  RAISE EXCEPTION 'ratings["%"] must be a number', k;
                END IF;

                n := (v::TEXT)::NUMERIC;
                IF n < 0.5 OR n > 5.0 THEN
                  RAISE EXCEPTION 'ratings["%"]=% , must be between 0.5 and 5.0', k, n;
                END IF;

                IF (n * 2) <> trunc(n * 2) THEN
                  RAISE EXCEPTION 'ratings["%"]=% , must be in 0.5 increments', k, n;
                END IF;
              END LOOP;

              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS posts_set_updated_at ON posts;
            CREATE TRIGGER posts_set_updated_at
            BEFORE UPDATE ON posts
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();

            DROP TRIGGER IF EXISTS posts_validate_ratings_trg ON posts;
            CREATE TRIGGER posts_validate_ratings_trg
            BEFORE INSERT OR UPDATE OF ratings ON posts
            FOR EACH ROW EXECUTE FUNCTION posts_validate_ratings();
        SQL);
  }

  public function down(): void
  {
    DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS posts_validate_ratings_trg ON posts;
            DROP TRIGGER IF EXISTS posts_set_updated_at ON posts;
            DROP TABLE IF EXISTS posts;
        SQL);
  }
};
