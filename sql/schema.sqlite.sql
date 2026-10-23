-- Integrity Autocars — SQLite schema (LOCAL DEVELOPMENT ONLY)
-- Auto-applied by sql/seed-dev.php when DB_DRIVER=sqlite.

CREATE TABLE IF NOT EXISTS staff_users (
  id            TEXT NOT NULL PRIMARY KEY,
  email         TEXT NOT NULL UNIQUE,
  name          TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'STAFF',
  is_active     INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS vehicles (
  id             TEXT PRIMARY KEY,
  make           TEXT NOT NULL,
  model          TEXT NOT NULL,
  year           INTEGER NOT NULL,
  slug           TEXT NOT NULL UNIQUE,
  price          INTEGER NOT NULL,
  offer_price    INTEGER,
  offer_start    TEXT,
  offer_end      TEXT,
  offer_terms    TEXT,
  mileage_km     INTEGER NOT NULL,
  engine_cc      INTEGER,
  fuel_type      TEXT NOT NULL DEFAULT 'Petrol',
  transmission   TEXT,
  drive_type     TEXT,
  exterior_color TEXT,
  condition      TEXT NOT NULL DEFAULT 'Used',
  chassis_number TEXT,
  description    TEXT,
  features       TEXT,
  status         TEXT NOT NULL DEFAULT 'AVAILABLE',
  published      INTEGER NOT NULL DEFAULT 1,
  featured       INTEGER NOT NULL DEFAULT 0,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_vehicle_status_pub ON vehicles (status, published);
CREATE INDEX IF NOT EXISTS idx_vehicle_created ON vehicles (created_at);

CREATE TABLE IF NOT EXISTS vehicle_images (
  id         TEXT PRIMARY KEY,
  vehicle_id TEXT NOT NULL REFERENCES vehicles (id) ON DELETE CASCADE,
  url        TEXT NOT NULL,
  is_cover   INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_vi_vehicle ON vehicle_images (vehicle_id);

CREATE TABLE IF NOT EXISTS enquiries (
  id               TEXT PRIMARY KEY,
  full_name        TEXT NOT NULL,
  email            TEXT NOT NULL,
  phone            TEXT NOT NULL,
  city             TEXT NOT NULL,
  address          TEXT,
  message          TEXT,
  vehicle_id       TEXT REFERENCES vehicles (id) ON DELETE SET NULL,
  vehicle_snapshot TEXT NOT NULL,
  status           TEXT NOT NULL DEFAULT 'NEW',
  assigned_to_id   TEXT REFERENCES staff_users (id) ON DELETE SET NULL,
  created_at       TEXT NOT NULL,
  updated_at       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_eq_status ON enquiries (status);

CREATE TABLE IF NOT EXISTS enquiry_notes (
  id         TEXT PRIMARY KEY,
  enquiry_id TEXT NOT NULL REFERENCES enquiries (id) ON DELETE CASCADE,
  staff_id   TEXT REFERENCES staff_users (id) ON DELETE SET NULL,
  content    TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS contact_messages (
  id         TEXT PRIMARY KEY,
  name       TEXT NOT NULL,
  phone      TEXT,
  email      TEXT,
  subject    TEXT NOT NULL,
  message    TEXT NOT NULL,
  status     TEXT NOT NULL DEFAULT 'NEW',
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invoices (
  id                 TEXT PRIMARY KEY,
  number             TEXT NOT NULL UNIQUE,
  enquiry_id         TEXT REFERENCES enquiries (id) ON DELETE SET NULL,
  customer_name      TEXT NOT NULL,
  customer_email     TEXT NOT NULL,
  customer_phone     TEXT NOT NULL,
  customer_city      TEXT NOT NULL,
  customer_address   TEXT,
  vehicle_snapshot   TEXT NOT NULL,
  price_at_issue     INTEGER NOT NULL,
  discount           INTEGER NOT NULL DEFAULT 0,
  additional_charges INTEGER NOT NULL DEFAULT 0,
  total              INTEGER NOT NULL,
  notes              TEXT NOT NULL,
  payment_status     TEXT NOT NULL DEFAULT 'UNPAID',
  share_token        TEXT UNIQUE,
  created_by_id      TEXT REFERENCES staff_users (id) ON DELETE SET NULL,
  created_at         TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS offers (
  id               TEXT PRIMARY KEY,
  number           TEXT NOT NULL UNIQUE,
  vehicle_id       TEXT NOT NULL REFERENCES vehicles (id) ON DELETE RESTRICT,
  vehicle_snapshot TEXT NOT NULL,
  customer_name    TEXT,
  customer_phone   TEXT,
  customer_email   TEXT,
  regular_price    INTEGER NOT NULL,
  offer_price      INTEGER NOT NULL,
  discount_percent INTEGER NOT NULL,
  saved_amount     INTEGER NOT NULL,
  valid_from       TEXT NOT NULL,
  valid_to         TEXT,
  terms            TEXT,
  status           TEXT NOT NULL DEFAULT 'ACTIVE',
  created_by_id    TEXT REFERENCES staff_users (id) ON DELETE SET NULL,
  created_at       TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS subscribers (
  id            TEXT PRIMARY KEY,
  email         TEXT NOT NULL UNIQUE,
  is_confirmed  INTEGER NOT NULL DEFAULT 0,
  confirm_token TEXT,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS site_settings (
  id                 TEXT NOT NULL PRIMARY KEY,
  business_name      TEXT NOT NULL DEFAULT 'Integrity Autocars',
  motto              TEXT NOT NULL DEFAULT 'OUR PROMISE DELIVERED',
  about_text         TEXT NOT NULL,
  phone_primary      TEXT NOT NULL DEFAULT '+255 756 428 835',
  phone_secondary    TEXT NOT NULL DEFAULT '+255 787 679 922',
  whatsapp_number    TEXT NOT NULL DEFAULT '+255756428835',
  display_email      TEXT NOT NULL DEFAULT 'integrityauto@gmail.com',
  instagram_url      TEXT NOT NULL DEFAULT 'https://www.instagram.com/integrityauto.co/',
  tiktok_url         TEXT,
  maps_url           TEXT NOT NULL DEFAULT 'https://maps.app.goo.gl/7t5BbBUmiYQmF8iA9',
  address            TEXT NOT NULL DEFAULT 'Kijitonyama, Dar es Salaam, Tanzania',
  invoice_notes      TEXT NOT NULL,
  legal_name         TEXT NOT NULL DEFAULT 'Integrity Roadworthy Company Limited',
  show_sold_publicly INTEGER NOT NULL DEFAULT 1,
  updated_at         TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS email_logs (
  id         TEXT PRIMARY KEY,
  type       TEXT NOT NULL,
  recipient  TEXT NOT NULL,
  subject    TEXT NOT NULL,
  success    INTEGER NOT NULL,
  error      TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         TEXT PRIMARY KEY,
  token      TEXT NOT NULL UNIQUE,
  staff_id   TEXT NOT NULL REFERENCES staff_users (id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  used_at    TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id         TEXT PRIMARY KEY,
  staff_id   TEXT REFERENCES staff_users (id) ON DELETE SET NULL,
  staff_name TEXT,
  action     TEXT NOT NULL,
  entity     TEXT NOT NULL,
  entity_id  TEXT,
  detail     TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_al_created ON audit_logs (created_at);

CREATE TABLE IF NOT EXISTS visit_daily (
  id      TEXT PRIMARY KEY,
  date    TEXT NOT NULL UNIQUE,
  visits  INTEGER NOT NULL DEFAULT 0,
  uniques INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS visitor_seen (
  id           TEXT PRIMARY KEY,
  visitor_hash TEXT NOT NULL,
  date         TEXT NOT NULL,
  UNIQUE (visitor_hash, date)
);

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket       TEXT NOT NULL,
  ip           TEXT NOT NULL,
  window_start INTEGER NOT NULL,
  count        INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, ip)
);
