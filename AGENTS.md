# AGENTS.md

Purpose: shared ground rules and quick context for any AI or human agent working on this Laravel API. Keep changes surgical, safe, and aligned with the existing style.

## Project Overview
- Framework: Laravel (API + web views)
- Auth: Laravel Sanctum (token/cookie based)
- Domain: Coffee shops, reviews, photos, and opening hours
- Key Areas:
  - API routes: `routes/api.php`
  - Web routes: `routes/web.php`
  - Controllers (API): `app/Http/Controllers/Api/*`
  - Models: `app/Models/*`
  - Requests: `app/Http/Requests/*`
  - Migrations: `database/migrations/*`
  - Config: `config/*`

## How To Work In This Repo
- Scope: Only change what the task requires. No drive‑by refactors.
- Files: Read related controller, model, request, migration, and route files before changing behavior.
- Style: Match existing Laravel conventions and project patterns. Prefer Eloquent, validation via Form Requests or `->validate`, and consistent JSON responses.
- Safety: Do not introduce new packages unless explicitly requested. Avoid breaking public endpoints.
- Tools: Use `apply_patch` to edit files. Do not `git commit` unless asked. Keep diffs minimal and focused.
- Secrets: Never commit or print secrets. Respect `.env` and `config/*` boundaries.
- Network/FS: Assume limited network. Don’t add external calls or write outside the workspace.

## API At A Glance (routes/api.php)
- Auth
  - `POST /auth/register`, `POST /auth/login`, `POST /auth/logout` (auth:sanctum)
  - `GET /auth/user/me` (auth:sanctum), `GET /auth/user/{id}` (auth:sanctum)
- Coffee Shops
  - `GET /coffee-shops`
  - `GET /coffee-shops/locations`
  - `GET /coffee-shops/{slug}` then `GET /coffee-shops/{shop}` (order matters: slug vs model binding)
  - Admin‑only (via controller middleware + Gate):
    - `POST /coffee-shops`
    - `PATCH /coffee-shops/{shop}`
    - `DELETE /coffee-shops/{shop}`
- Shop Hours (nested under a shop)
  - `GET /coffee-shops/{shop}/hours`
  - `POST /coffee-shops/{shop}/hours`
  - `GET|PATCH|DELETE /coffee-shops/{shop}/hours/{day}/{open}`
- Reviews
  - `GET /coffee-shops/{shop}/reviews`
  - `GET /reviews/{post}`
  - Auth required: `POST /coffee-shops/{shop}/reviews`, `PATCH /reviews/{post}`, `DELETE /reviews/{post}`
- Photos
  - `GET /coffee-shops/{shop}/photos`, `POST /coffee-shops/{shop}/photos`
  - `GET|PATCH|DELETE /photos/{photo}`
- User content
  - `GET /users/me/posts` (auth:sanctum)

## Authorization & Validation
- Auth: Use `auth:sanctum` for protected routes. Rely on existing middleware wiring in controllers when present (e.g., CoffeeShop admin actions).
- Gates/Policies: Respect `can:manage-coffee-shops` checks for admin endpoints. Don’t bypass or duplicate in routes if controller already applies them.
- Validation: Prefer Form Request classes for complex inputs; otherwise use `$request->validate([...])`. Keep error codes consistent: 422 for validation, 401/403 for authz, 404 for not found, 409 for conflicts, 200/201/204 for success.

## Data & Migrations
- Migrations live in `database/migrations`. If altering schema:
  - Add a new migration; do not edit old ones that have shipped.
  - Keep names chronological and descriptive.
  - Provide reversible `down()` logic where possible.
- Models define relationships and casts. Use them rather than raw queries when feasible.
- Be mindful of Postgres array handling (e.g., `tags` on `coffee_shops` is serialized as a text[] literal in `CoffeeShopController::store`). Preserve current encoding/format unless explicitly changing.

## Responses & Errors
- JSON only for API. Use `response()->json($payload, $status)`.
- Pagination: Prefer Laravel paginator/length‑aware when listing potentially large sets.
- Consistency: Include a clear message for errors. Avoid leaking stack traces.

## Files, Storage, and CORS
- File uploads and remote storage are configured via `config/filesystems.php` and `config/services.php`. Respect disk choices and URL generation already present in controllers.
- CORS is managed in `config/cors.php`. If adding new public endpoints, ensure CORS stays appropriate.

## Common Playbooks
- Add a new API endpoint
  1) Define route in `routes/api.php` with correct middleware.
  2) Implement method in the appropriate `Api\*Controller`.
  3) Validate input via Form Request or `$request->validate`.
  4) Use Eloquent; return JSON with correct status code.
  5) Add conflict/exists checks and 404s where applicable.
- Extend a model with a relation or cast
  1) Update `app/Models/*` minimally.
  2) If schema changes, add a migration.
  3) Prefer accessors/mutators for serialization changes.
- Add a migration
  1) Create a new timestamped migration.
  2) Keep `down()` safe and mirrored.
  3) Don’t modify old migrations.

## Quality Bar
- Keep patches minimal, focused, and consistent with surrounding code.
- Don’t introduce global behavior changes through config unless requested.
- Use explicit types and constraints in validation.
- Avoid N+1 queries; eager‑load where needed.
- Prefer small, composable controller methods; avoid monolith methods.

## Local Runbook (for humans)
- Install deps: `composer install`
- Env: copy `.env.example` → `.env`, configure DB + `SANCTUM_STATEFUL_DOMAINS` if using SPA/cookies
- Generate key: `php artisan key:generate`
- Migrate: `php artisan migrate`
- Serve: `php artisan serve` (or via your web server setup)

## When In Doubt
- Read `routes/api.php` and the corresponding controller/model before changing behavior.
- Preserve route order when there is potential conflict (e.g., slug vs ID bindings).
- Don’t add dependencies, services, or external I/O without an explicit requirement.
- Ask for clarification if a change would affect authz, data shape, or public endpoints.

Database Schema are as follows: (Note that the users schema is the default larvel generated users/athentication)

-- =========================================
-- RateMyCoffee: Core SQL Schema (PostgreSQL)
-- =========================================

-- Extensions (safe no-ops if already enabled)
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
-- OPTIONAL: enable if PostGIS is installed (otherwise leave commented)
-- CREATE EXTENSION IF NOT EXISTS postgis;

-- =======================
-- ENUM TYPES
-- =======================
CREATE TYPE post_status AS ENUM ('draft','published','flagged','removed');
CREATE TYPE shop_status AS ENUM ('active','temporarily_closed','permanently_closed','draft','pending_verification');
CREATE TYPE price_tier  AS ENUM ('₱','₱₱','₱₱₱');

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

-- =======================
-- COFFEE SHOPS
-- =======================
CREATE TABLE coffee_shops (
  id                    BIGSERIAL PRIMARY KEY,

  -- Identity
  name                  TEXT NOT NULL,
  slug                  TEXT UNIQUE,
  status                shop_status NOT NULL DEFAULT 'active',
  description           TEXT,

  -- Address (PH context)
  country_code          CHAR(2) NOT NULL DEFAULT 'PH',
  region                TEXT,
  province              TEXT,
  city_municipality     TEXT,
  barangay              TEXT,
  street_address        TEXT,
  postcode              TEXT,

  -- Coordinates (non-PostGIS; keep even if you also use PostGIS)
  latitude              NUMERIC(9,6) CHECK (latitude >= -90 AND latitude <= 90),
  longitude             NUMERIC(9,6) CHECK (longitude >= -180 AND longitude <= 180),

  -- Contact & presence
  phone                 TEXT,
  email                 TEXT,
  website_url           TEXT,
  facebook_url          TEXT,
  instagram_handle      TEXT,
  google_maps_url       TEXT,

  -- Commercial hints
  price                 price_tier,
  accepts_gcash         BOOLEAN DEFAULT NULL,
  accepts_cards         BOOLEAN DEFAULT NULL,

  -- Amenities
  has_wifi              BOOLEAN DEFAULT NULL,
  has_outlets           BOOLEAN DEFAULT NULL,
  outdoor_seating       BOOLEAN DEFAULT NULL,
  parking_available     BOOLEAN DEFAULT NULL,
  wheelchair_accessible BOOLEAN DEFAULT NULL,
  pet_friendly          BOOLEAN DEFAULT NULL,
  vegan_options         BOOLEAN DEFAULT NULL,
  manual_brew           BOOLEAN DEFAULT NULL,
  decaf_available       BOOLEAN DEFAULT NULL,

  tags                  TEXT[] DEFAULT '{}'::TEXT[],

  -- Claiming
  claimed_by_user_id    BIGINT REFERENCES users(id) ON DELETE SET NULL,
  claiming_notes        TEXT,

  -- Ratings cache (optional; can be maintained via job)
  rating_overall_cache  NUMERIC(3,2),
  rating_count_cache    INTEGER,

-- Search
search_tsv tsvector GENERATED ALWAYS AS (
    public.coffee_shop_search_tsv(name, city_municipality, province, tags)
) STORED,

  created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Avoid obvious duplicates (name + city/province, case-insensitive)
CREATE UNIQUE INDEX coffee_shops_unique_name_city_prov
  ON coffee_shops (lower(name), lower(coalesce(city_municipality,'')), lower(coalesce(province,'')));

-- Indexes
CREATE INDEX coffee_shops_status_idx     ON coffee_shops(status);
CREATE INDEX coffee_shops_geo_idx        ON coffee_shops(latitude, longitude);
CREATE INDEX coffee_shops_city_idx       ON coffee_shops(lower(city_municipality));
CREATE INDEX coffee_shops_trgm_name_idx  ON coffee_shops USING GIN (name gin_trgm_ops);
CREATE INDEX coffee_shops_search_tsv_gin ON coffee_shops USING GIN (search_tsv);

-- Hours (multiple intervals per day allowed)
CREATE TABLE shop_hours (
  shop_id        BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
  day_of_week    SMALLINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Sun … 6=Sat
  open_time      TIME,
  close_time     TIME,
  is_24h         BOOLEAN NOT NULL DEFAULT FALSE,
  notes          TEXT,
  PRIMARY KEY (shop_id, day_of_week, open_time)
);

-- Shop photos
CREATE TABLE shop_photos (
  id           BIGSERIAL PRIMARY KEY,
  shop_id      BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
  url          TEXT NOT NULL,
  caption      TEXT,
  is_cover     BOOLEAN NOT NULL DEFAULT FALSE,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX shop_photos_shop_idx ON shop_photos(shop_id, is_cover, sort_order);

-- Alternate names
CREATE TABLE shop_aliases (
  shop_id   BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
  alias     TEXT NOT NULL,
  PRIMARY KEY (shop_id, alias)
);

-- Updated-at trigger (reusable)
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER coffee_shops_set_updated_at
BEFORE UPDATE ON coffee_shops
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- =======================
-- POSTS (REVIEWS)
-- =======================
CREATE TABLE posts (
  id                  BIGSERIAL PRIMARY KEY,
  shop_id             BIGINT NOT NULL REFERENCES coffee_shops(id) ON DELETE CASCADE,
  author_user_id      BIGINT REFERENCES users(id) ON DELETE SET NULL,
  is_anonymous        BOOLEAN NOT NULL DEFAULT FALSE,

  body                TEXT,

  -- Per-aspect ratings (0.5–5.0 step 0.5, validated by trigger below)
  ratings             JSONB NOT NULL,

  -- Visit & spend metadata
  visited_at          DATE,
  spend_php           NUMERIC(10,2) CHECK (spend_php >= 0),
  ordered_items       TEXT[] DEFAULT '{}'::TEXT[],

  -- Optional flavor sliders (free-form)
  taste_profile       JSONB,                 -- e.g., {"acidity":3.5,"body":4}

  seat_context        TEXT,
  internet_speed_mbps NUMERIC(6,2) CHECK (internet_speed_mbps >= 0),

  -- Moderation & lifecycle
  status              post_status NOT NULL DEFAULT 'published',
  flagged_count       INTEGER NOT NULL DEFAULT 0,
  admin_notes         TEXT,
  deleted_at          TIMESTAMPTZ,

  -- Privacy-safe client metadata
  ip_hash             BYTEA,
  user_agent          TEXT,

  -- Timestamps
  created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),

  -- Generated overall score: average of present rating keys
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

-- Indexes
CREATE INDEX posts_shop_idx   ON posts(shop_id);
CREATE INDEX posts_author_idx ON posts(author_user_id) WHERE author_user_id IS NOT NULL;
CREATE INDEX posts_status_idx ON posts(status);
CREATE INDEX posts_created_idx ON posts(created_at DESC);
CREATE INDEX posts_ratings_gin ON posts USING GIN (ratings jsonb_path_ops);

-- Unique: one published/draft review per user per shop
CREATE UNIQUE INDEX posts_unique_user_shop ON posts(shop_id, author_user_id)
  WHERE author_user_id IS NOT NULL AND status IN ('published','draft');

-- Updated-at trigger
CREATE TRIGGER posts_set_updated_at
BEFORE UPDATE ON posts
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Validate ratings (0.5..5.0, in 0.5 steps, numeric only)
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

    n := (v::TEXT)::NUMERIC;  -- cast json number text to numeric
    IF n < 0.5 OR n > 5.0 THEN
      RAISE EXCEPTION 'ratings["%"]=%, must be between 0.5 and 5.0', k, n;
    END IF;

    -- enforce 0.5 step: n*2 must be integer
    IF (n * 2) <> trunc(n * 2) THEN
      RAISE EXCEPTION 'ratings["%"]=%, must be in 0.5 increments', k, n;
    END IF;
  END LOOP;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER posts_validate_ratings_trg
BEFORE INSERT OR UPDATE OF ratings ON posts
FOR EACH ROW EXECUTE FUNCTION posts_validate_ratings();

-- =======================
-- POST PHOTOS & VOTES
-- =======================
CREATE TABLE post_photos (
  id        BIGSERIAL PRIMARY KEY,
  post_id   BIGINT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  url       TEXT NOT NULL,
  caption   TEXT,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX post_photos_post_idx ON post_photos(post_id, sort_order);

CREATE TABLE post_votes (
  post_id   BIGINT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  user_id   BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  is_helpful BOOLEAN NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (post_id, user_id)
);

-- =======================
-- RATINGS VIEW (live)
-- =======================
CREATE OR REPLACE VIEW shop_rating_stats AS
SELECT
  p.shop_id,
  COUNT(*)::INT AS rating_count,
  ROUND(AVG(p.overall_score)::numeric, 2) AS rating_overall
FROM posts p
WHERE p.status = 'published' AND p.overall_score IS NOT NULL
GROUP BY p.shop_id;

---
This file is intended to be always treated as context for any agent making changes here. Adhere to these constraints to keep the API consistent and safe.
