<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS pg_trgm;

            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'shop_status') THEN
                    CREATE TYPE shop_status AS ENUM ('active','temporarily_closed','permanently_closed','draft','pending_verification');
                END IF;
            END$$;

            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'price_tier') THEN
                    CREATE TYPE price_tier AS ENUM ('₱','₱₱','₱₱₱');
                END IF;
            END$$;

            CREATE OR REPLACE FUNCTION public.coffee_shop_search_tsv(
                p_name                TEXT,
                p_city_municipality   TEXT,
                p_province            TEXT,
                p_tags                TEXT[]
            ) RETURNS tsvector
            LANGUAGE sql
            IMMUTABLE
            AS $$
                SELECT
                    setweight(to_tsvector('simple'::regconfig, coalesce(p_name,'')),               'A'::"char") ||
                    setweight(to_tsvector('simple'::regconfig, coalesce(p_city_municipality,'')), 'B'::"char") ||
                    setweight(to_tsvector('simple'::regconfig, coalesce(p_province,'')),          'C'::"char") ||
                    setweight(to_tsvector('simple'::regconfig,
                                coalesce(array_to_string(p_tags,' '),'')),                       'D'::"char");
            $$;

            CREATE TABLE IF NOT EXISTS coffee_shops (
            id                    BIGSERIAL PRIMARY KEY,

            name                  TEXT NOT NULL,
            slug                  TEXT UNIQUE,
            status                shop_status NOT NULL DEFAULT 'active',

            country_code          CHAR(2) NOT NULL DEFAULT 'PH',
            region                TEXT,
            province              TEXT,
            city_municipality     TEXT,
            barangay              TEXT,
            street_address        TEXT,
            postcode              TEXT,

            latitude              NUMERIC(9,6) CHECK (latitude >= -90 AND latitude <= 90),
            longitude             NUMERIC(9,6) CHECK (longitude >= -180 AND longitude <= 180),

            phone                 TEXT,
            email                 TEXT,
            website_url           TEXT,
            facebook_url          TEXT,
            instagram_handle      TEXT,
            google_maps_url       TEXT,
            description           TEXT,

            price                 price_tier,
            accepts_gcash         BOOLEAN NOT NULL DEFAULT TRUE,
            accepts_cards         BOOLEAN NOT NULL DEFAULT TRUE,

            has_wifi              BOOLEAN NOT NULL DEFAULT TRUE,
            has_outlets           BOOLEAN NOT NULL DEFAULT TRUE,
            outdoor_seating       BOOLEAN NOT NULL DEFAULT FALSE,
            parking_available     BOOLEAN NOT NULL DEFAULT FALSE,
            wheelchair_accessible BOOLEAN NOT NULL DEFAULT FALSE,
            pet_friendly          BOOLEAN NOT NULL DEFAULT FALSE,
            vegan_options         BOOLEAN NOT NULL DEFAULT FALSE,
            manual_brew           BOOLEAN NOT NULL DEFAULT FALSE,
            decaf_available       BOOLEAN NOT NULL DEFAULT FALSE,

            tags                  TEXT[] DEFAULT '{}'::TEXT[],

            claimed_by_user_id    BIGINT REFERENCES users(id) ON DELETE SET NULL,
            claiming_notes        TEXT,

            rating_overall_cache  NUMERIC(3,2),
            rating_count_cache    INTEGER,

            search_tsv tsvector GENERATED ALWAYS AS (
                public.coffee_shop_search_tsv(name, city_municipality, province, tags)
            ) STORED,

            created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );

            CREATE UNIQUE INDEX IF NOT EXISTS coffee_shops_unique_name_city_prov
            ON coffee_shops (lower(name), lower(coalesce(city_municipality,'')), lower(coalesce(province,'')));

            CREATE INDEX IF NOT EXISTS coffee_shops_status_idx     ON coffee_shops(status);
            CREATE INDEX IF NOT EXISTS coffee_shops_geo_idx        ON coffee_shops(latitude, longitude);
            CREATE INDEX IF NOT EXISTS coffee_shops_city_idx       ON coffee_shops(lower(city_municipality));
            CREATE INDEX IF NOT EXISTS coffee_shops_trgm_name_idx  ON coffee_shops USING GIN (name gin_trgm_ops);
            CREATE INDEX IF NOT EXISTS coffee_shops_search_tsv_gin ON coffee_shops USING GIN (search_tsv);

            CREATE OR REPLACE FUNCTION set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
            NEW.updated_at = NOW();
            RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS coffee_shops_set_updated_at ON coffee_shops;
            CREATE TRIGGER coffee_shops_set_updated_at
            BEFORE UPDATE ON coffee_shops
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS coffee_shops_set_updated_at ON coffee_shops;
            DROP TABLE IF EXISTS coffee_shops;
            -- Note: keep function/types for other objects that may depend on them
            SQL);
    }
};
