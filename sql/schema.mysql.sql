-- =============================================================================
-- Integrity Autocars — MySQL schema (production, cPanel + phpMyAdmin)
-- Import via phpMyAdmin → Import (or: mysql -u USER -p DBNAME < schema.mysql.sql)
-- Engine InnoDB, charset utf8mb4. Money = INT (whole Tanzanian Shillings).
-- Status fields are VARCHAR on purpose (parity with the previous SQLite data).
-- =============================================================================

CREATE TABLE IF NOT EXISTS staff_users (
  id            VARCHAR(32)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  name          VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(10)  NOT NULL DEFAULT 'STAFF',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
  id             VARCHAR(32)  NOT NULL,
  make           VARCHAR(80)  NOT NULL,
  model          VARCHAR(120) NOT NULL,
  year           INT          NOT NULL,
  slug           VARCHAR(80)  NOT NULL,
  price          INT          NOT NULL,
  offer_price    INT          NULL,
  offer_start    DATETIME     NULL,
  offer_end      DATETIME     NULL,
  offer_terms    TEXT         NULL,
  mileage_km     INT          NOT NULL,
  engine_cc      INT          NULL,
  fuel_type      VARCHAR(20)  NOT NULL DEFAULT 'Petrol',
  transmission   VARCHAR(40)  NULL,
  drive_type     VARCHAR(40)  NULL,
  exterior_color VARCHAR(80)  NULL,
  condition      VARCHAR(10)  NOT NULL DEFAULT 'Used',
  chassis_number VARCHAR(80)  NULL,
  description    TEXT         NULL,
  features       TEXT         NULL,
  status         VARCHAR(12)  NOT NULL DEFAULT 'AVAILABLE',
  published      TINYINT(1)   NOT NULL DEFAULT 1,
  featured       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vehicle_slug (slug),
  KEY idx_vehicle_status_pub (status, published),
  KEY idx_vehicle_make (make),
  KEY idx_vehicle_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_images (
  id         VARCHAR(32)  NOT NULL,
  vehicle_id VARCHAR(32)  NOT NULL,
  url        VARCHAR(600) NOT NULL,
  is_cover   TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_vi_vehicle (vehicle_id),
  CONSTRAINT fk_vi_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiries (
  id              VARCHAR(32)  NOT NULL,
  full_name       VARCHAR(120) NOT NULL,
  email           VARCHAR(200) NOT NULL,
  phone           VARCHAR(40)  NOT NULL,
  city            VARCHAR(120) NOT NULL,
  address         VARCHAR(300) NULL,
  message         TEXT         NULL,
  vehicle_id      VARCHAR(32)  NULL,
  vehicle_snapshot TEXT       NOT NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'NEW',
  assigned_to_id  VARCHAR(32)  NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_eq_vehicle (vehicle_id),
  KEY idx_eq_assigned (assigned_to_id),
  KEY idx_eq_status (status),
  KEY idx_eq_created (created_at),
  CONSTRAINT fk_eq_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL,
  CONSTRAINT fk_eq_assigned FOREIGN KEY (assigned_to_id) REFERENCES staff_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiry_notes (
  id         VARCHAR(32) NOT NULL,
  enquiry_id VARCHAR(32) NOT NULL,
  staff_id   VARCHAR(32) NULL,
  content    TEXT        NOT NULL,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_en_enquiry (enquiry_id),
  KEY idx_en_staff (staff_id),
  CONSTRAINT fk_en_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE,
  CONSTRAINT fk_en_staff FOREIGN KEY (staff_id) REFERENCES staff_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id         VARCHAR(32)  NOT NULL,
  name       VARCHAR(120) NOT NULL,
  phone      VARCHAR(40)  NULL,
  email      VARCHAR(200) NULL,
  subject    VARCHAR(200) NOT NULL,
  message    TEXT         NOT NULL,
  status     VARCHAR(12)  NOT NULL DEFAULT 'NEW',
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cm_status (status),
  KEY idx_cm_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id                 VARCHAR(32)  NOT NULL,
  number             VARCHAR(20)  NOT NULL,
  enquiry_id         VARCHAR(32)  NULL,
  customer_name      VARCHAR(200) NOT NULL,
  customer_email     VARCHAR(200) NOT NULL,
  customer_phone     VARCHAR(40)  NOT NULL,
  customer_city      VARCHAR(120) NOT NULL,
  customer_address   VARCHAR(300) NULL,
  vehicle_snapshot   TEXT         NOT NULL,
  price_at_issue     INT          NOT NULL,
  discount           INT          NOT NULL DEFAULT 0,
  additional_charges INT          NOT NULL DEFAULT 0,
  total              INT          NOT NULL,
  notes              TEXT         NOT NULL,
  payment_status     VARCHAR(10)  NOT NULL DEFAULT 'UNPAID',
  share_token        CHAR(48)     NULL,
  created_by_id      VARCHAR(32)  NULL,
  created_at         DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_number (number),
  UNIQUE KEY uq_invoice_token (share_token),
  KEY idx_inv_enquiry (enquiry_id),
  KEY idx_inv_created_by (created_by_id),
  CONSTRAINT fk_inv_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_creator FOREIGN KEY (created_by_id) REFERENCES staff_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
  id               VARCHAR(32) NOT NULL,
  number           VARCHAR(20) NOT NULL,
  vehicle_id       VARCHAR(32) NOT NULL,
  vehicle_snapshot TEXT        NOT NULL,
  customer_name    VARCHAR(200) NULL,
  customer_phone   VARCHAR(40)  NULL,
  customer_email   VARCHAR(200) NULL,
  regular_price    INT          NOT NULL,
  offer_price      INT          NOT NULL,
  discount_percent INT          NOT NULL,
  saved_amount     INT          NOT NULL,
  valid_from       DATETIME     NOT NULL,
  valid_to         DATETIME     NULL,
  terms            TEXT         NULL,
  status           VARCHAR(10)  NOT NULL DEFAULT 'ACTIVE',
  created_by_id    VARCHAR(32)  NULL,
  created_at       DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_offer_number (number),
  KEY idx_off_vehicle (vehicle_id),
  KEY idx_off_created_by (created_by_id),
  CONSTRAINT fk_off_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
  CONSTRAINT fk_off_creator FOREIGN KEY (created_by_id) REFERENCES staff_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscribers (
  id            VARCHAR(32)  NOT NULL,
  email         VARCHAR(254) NOT NULL,
  is_confirmed  TINYINT(1)   NOT NULL DEFAULT 0,
  confirm_token CHAR(64)     NULL,
  created_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub_email (email),
  KEY idx_sub_token (confirm_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  id                 VARCHAR(20)  NOT NULL DEFAULT 'settings',
  business_name      VARCHAR(120) NOT NULL DEFAULT 'Integrity Autocars',
  motto              VARCHAR(120) NOT NULL DEFAULT 'OUR PROMISE DELIVERED',
  about_text         TEXT         NOT NULL,
  phone_primary      VARCHAR(40)  NOT NULL DEFAULT '+255 756 428 835',
  phone_secondary    VARCHAR(40)  NOT NULL DEFAULT '+255 787 679 922',
  whatsapp_number    VARCHAR(40)  NOT NULL DEFAULT '+255756428835',
  display_email      VARCHAR(200) NOT NULL DEFAULT 'integrityauto@gmail.com',
  instagram_url      VARCHAR(300) NOT NULL DEFAULT 'https://www.instagram.com/integrityauto.co/',
  tiktok_url         VARCHAR(300) NULL,
  maps_url           VARCHAR(300) NOT NULL DEFAULT 'https://maps.app.goo.gl/7t5BbBUmiYQmF8iA9',
  address            VARCHAR(300) NOT NULL DEFAULT 'Kijitonyama, Dar es Salaam, Tanzania',
  invoice_notes      TEXT         NOT NULL,
  legal_name         VARCHAR(200) NOT NULL DEFAULT 'Integrity Roadworthy Company Limited',
  show_sold_publicly TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at         DATETIME     NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
  id         VARCHAR(32)  NOT NULL,
  type       VARCHAR(40)  NOT NULL,
  recipient  VARCHAR(254) NOT NULL,
  subject    VARCHAR(300) NOT NULL,
  success    TINYINT(1)   NOT NULL,
  error      VARCHAR(500) NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_el_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         VARCHAR(32) NOT NULL,
  token      CHAR(48)    NOT NULL,
  staff_id   VARCHAR(32) NOT NULL,
  expires_at DATETIME    NOT NULL,
  used_at    DATETIME    NULL,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prt_token (token),
  KEY idx_prt_staff (staff_id),
  CONSTRAINT fk_prt_staff FOREIGN KEY (staff_id) REFERENCES staff_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id         VARCHAR(32)  NOT NULL,
  staff_id   VARCHAR(32)  NULL,
  staff_name VARCHAR(120) NULL,
  action     VARCHAR(40)  NOT NULL,
  entity     VARCHAR(40)  NOT NULL,
  entity_id  VARCHAR(32)  NULL,
  detail     VARCHAR(500) NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_al_staff (staff_id),
  KEY idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_daily (
  id      VARCHAR(32) NOT NULL,
  date    DATE        NOT NULL,
  visits  INT         NOT NULL DEFAULT 0,
  uniques INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vd_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_seen (
  id           VARCHAR(32) NOT NULL,
  visitor_hash CHAR(16)    NOT NULL,
  date         DATE        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vs_hash_date (visitor_hash, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket       VARCHAR(40) NOT NULL,
  ip           VARCHAR(64) NOT NULL,
  window_start BIGINT      NOT NULL,
  count        INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
