-- ============================================================
-- Accounting System — Complete MySQL Schema (FULL)
-- Import ONLY this file in phpMyAdmin / MySQL CLI
-- Compatible with Laragon · MySQL 5.7+ / MariaDB
-- ============================================================
-- Includes:
--   Users, Settings, Financial Years
--   3-level Chart of Accounts (Main → Sub → Subsidiary) EMPTY
--   Cash Receipt Voucher (CRV) master + detail
--   Cash Payment Voucher (CPV) master + detail
--   Journal Voucher (JV) master + detail
--   Shared voucher sequence across CRV + CPV + JV (per FY)
--   Item Categories + Items master (for Purchase later)
--   Ledger (double-entry) + Audit Trail
--   NO default Chart of Accounts / item seeds (create from UI)
--   NO separate migrate files required
-- ============================================================

CREATE DATABASE IF NOT EXISTS accounting_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE accounting_system;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_trail;
DROP TABLE IF EXISTS ledger;
DROP TABLE IF EXISTS journal_voucher_details;
DROP TABLE IF EXISTS journal_vouchers;
DROP TABLE IF EXISTS cash_payment_voucher_details;
DROP TABLE IF EXISTS cash_payment_vouchers;
DROP TABLE IF EXISTS cash_receipt_voucher_details;
DROP TABLE IF EXISTS cash_receipt_vouchers;
DROP TABLE IF EXISTS voucher_sequence;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS item_categories;
DROP TABLE IF EXISTS subsidiary_heads;
DROP TABLE IF EXISTS sub_heads;
DROP TABLE IF EXISTS main_heads;
DROP TABLE IF EXISTS financial_years;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NULL,
  phone         VARCHAR(30)  NULL,
  role          ENUM('admin','accountant','viewer') NOT NULL DEFAULT 'accountant',
  avatar        VARCHAR(255) NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: username=admin  password=admin123 (hash set on first login)
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2y$10$placeholder.will.be.replaced.on.login', 'System Administrator', 'admin@accounting.local', 'admin');

-- ------------------------------------------------------------
-- Settings (key-value)
-- ------------------------------------------------------------
CREATE TABLE settings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(80)  NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Accounting System'),
('company_address', ''),
('currency_symbol', 'Rs.'),
('currency_code', 'PKR'),
('fy_start_month', '7'),
('cash_account_code', ''),
('date_format', 'Y-m-d');

-- ------------------------------------------------------------
-- Financial Years (voucher numbers reset each FY)
-- July–June style (Pakistan common); active FY seeded for 2026-2027
-- ------------------------------------------------------------
CREATE TABLE financial_years (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(20)  NOT NULL UNIQUE,
  title       VARCHAR(80)  NOT NULL,
  start_date  DATE NOT NULL,
  end_date    DATE NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 0,
  is_closed   TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO financial_years (code, title, start_date, end_date, is_active) VALUES
('2025-2026', 'FY 2025-2026', '2025-07-01', '2026-06-30', 0),
('2026-2027', 'FY 2026-2027', '2026-07-01', '2027-06-30', 1);

-- ------------------------------------------------------------
-- Chart of Accounts — Level 1: Main Heads
-- Code auto: 01, 02, 03… (not editable in UI)
-- EMPTY by default — create from UI
-- ------------------------------------------------------------
CREATE TABLE main_heads (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(10)  NOT NULL UNIQUE,
  title      VARCHAR(150) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mh_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Level 2: Sub Heads
-- code under main: 01, 02…  · full_code e.g. 0101
-- ------------------------------------------------------------
CREATE TABLE sub_heads (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  main_head_id  INT UNSIGNED NOT NULL,
  code          VARCHAR(10)  NOT NULL,
  full_code     VARCHAR(20)  NOT NULL UNIQUE,
  title         VARCHAR(150) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sub_per_main (main_head_id, code),
  CONSTRAINT fk_sh_main FOREIGN KEY (main_head_id) REFERENCES main_heads(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sh_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Level 3: Subsidiary Heads (Ledger Accounts)
-- Code structure: Main(2) + Sub(2) + Subsidiary(5) e.g. 010100001
-- Opening balance / nature set later from Chart of Accounts UI
-- EMPTY by default
-- ------------------------------------------------------------
CREATE TABLE subsidiary_heads (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sub_head_id     INT UNSIGNED NOT NULL,
  code            VARCHAR(10)  NOT NULL,
  full_code       VARCHAR(30)  NOT NULL UNIQUE,
  title           VARCHAR(150) NOT NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  nature          ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
  account_type    ENUM('cash','bank','party','general') NOT NULL DEFAULT 'general',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subsi_per_sub (sub_head_id, code),
  CONSTRAINT fk_subsi_sub FOREIGN KEY (sub_head_id) REFERENCES sub_heads(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_subsi_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Shared Voucher Sequence (CRV + CPV + JV common transaction sequence)
-- voucher_no is type-wise per financial year (1, 2, 3…)
-- sequence_no is shared across CRV/CPV/JV within FY
-- ------------------------------------------------------------
CREATE TABLE voucher_sequence (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  sequence_no        INT UNSIGNED NOT NULL,
  voucher_type       ENUM('CRV','CPV','JV','PUR','BRV','BPV') NOT NULL,
  voucher_id         INT UNSIGNED NOT NULL,
  voucher_no         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  voucher_date       DATE NOT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fy_seq (financial_year_id, sequence_no),
  UNIQUE KEY uq_fy_type_no (financial_year_id, voucher_type, voucher_no),
  KEY idx_voucher_ref (voucher_ref),
  CONSTRAINT fk_seq_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Cash Receipt Voucher (Master)
-- UI: pure double-entry rows (Debit/Credit). cash_account_id inferred from lines.
-- ------------------------------------------------------------
CREATE TABLE cash_receipt_vouchers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  voucher_no         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  voucher_date       DATE NOT NULL,
  cash_account_id    INT UNSIGNED NOT NULL COMMENT 'Inferred cash/bank account from lines',
  total_amount       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  narration          VARCHAR(500) NULL COMMENT 'Optional summary from first line narration',
  status             ENUM('posted','void') NOT NULL DEFAULT 'posted',
  created_by         INT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_crv_fy_no (financial_year_id, voucher_no),
  UNIQUE KEY uq_crv_ref (voucher_ref),
  CONSTRAINT fk_crv_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id),
  CONSTRAINT fk_crv_cash FOREIGN KEY (cash_account_id) REFERENCES subsidiary_heads(id),
  CONSTRAINT fk_crv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cash_receipt_voucher_details (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id       INT UNSIGNED NOT NULL,
  line_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subsidiary_id    INT UNSIGNED NOT NULL,
  narration        VARCHAR(500) NULL,
  amount           DECIMAL(18,2) NOT NULL,
  previous_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_crvd_master FOREIGN KEY (voucher_id) REFERENCES cash_receipt_vouchers(id) ON DELETE CASCADE,
  CONSTRAINT fk_crvd_subsi FOREIGN KEY (subsidiary_id) REFERENCES subsidiary_heads(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Cash Payment Voucher (Master)
-- ------------------------------------------------------------
CREATE TABLE cash_payment_vouchers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  voucher_no         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  voucher_date       DATE NOT NULL,
  cash_account_id    INT UNSIGNED NOT NULL COMMENT 'Inferred cash/bank account from lines',
  total_amount       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  narration          VARCHAR(500) NULL,
  status             ENUM('posted','void') NOT NULL DEFAULT 'posted',
  created_by         INT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cpv_fy_no (financial_year_id, voucher_no),
  UNIQUE KEY uq_cpv_ref (voucher_ref),
  CONSTRAINT fk_cpv_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id),
  CONSTRAINT fk_cpv_cash FOREIGN KEY (cash_account_id) REFERENCES subsidiary_heads(id),
  CONSTRAINT fk_cpv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cash_payment_voucher_details (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id       INT UNSIGNED NOT NULL,
  line_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subsidiary_id    INT UNSIGNED NOT NULL,
  narration        VARCHAR(500) NULL,
  amount           DECIMAL(18,2) NOT NULL,
  previous_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_cpvd_master FOREIGN KEY (voucher_id) REFERENCES cash_payment_vouchers(id) ON DELETE CASCADE,
  CONSTRAINT fk_cpvd_subsi FOREIGN KEY (subsidiary_id) REFERENCES subsidiary_heads(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Journal Voucher (Master) — pure double-entry Dr/Cr lines
-- Shared sequence_no with CRV/CPV; own voucher_no per FY
-- ------------------------------------------------------------
CREATE TABLE journal_vouchers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  voucher_no         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  voucher_date       DATE NOT NULL,
  total_debit        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total_credit       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  narration          VARCHAR(500) NULL,
  status             ENUM('posted','void') NOT NULL DEFAULT 'posted',
  created_by         INT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_jv_fy_no (financial_year_id, voucher_no),
  UNIQUE KEY uq_jv_ref (voucher_ref),
  CONSTRAINT fk_jv_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id),
  CONSTRAINT fk_jv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE journal_voucher_details (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id       INT UNSIGNED NOT NULL,
  line_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subsidiary_id    INT UNSIGNED NOT NULL,
  narration        VARCHAR(500) NULL,
  debit_amount     DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  credit_amount    DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  previous_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_jvd_master FOREIGN KEY (voucher_id) REFERENCES journal_vouchers(id) ON DELETE CASCADE,
  CONSTRAINT fk_jvd_subsi FOREIGN KEY (subsidiary_id) REFERENCES subsidiary_heads(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Ledger (immutable double-entry journal lines)
-- ------------------------------------------------------------
CREATE TABLE ledger (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  sequence_no        INT UNSIGNED NOT NULL,
  voucher_type       ENUM('CRV','CPV','JV','PUR','BRV','BPV','OB') NOT NULL,
  voucher_id         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  voucher_date       DATE NOT NULL,
  subsidiary_id      INT UNSIGNED NOT NULL,
  narration          VARCHAR(500) NULL,
  debit              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  credit             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  balance_after      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  created_by         INT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ledger_account_date (subsidiary_id, voucher_date, id),
  KEY idx_ledger_fy (financial_year_id),
  KEY idx_ledger_ref (voucher_ref),
  CONSTRAINT fk_led_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id),
  CONSTRAINT fk_led_subsi FOREIGN KEY (subsidiary_id) REFERENCES subsidiary_heads(id),
  CONSTRAINT fk_led_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Audit Trail
-- ------------------------------------------------------------
CREATE TABLE audit_trail (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(50)  NOT NULL,
  module      VARCHAR(50)  NOT NULL,
  record_id   VARCHAR(50)  NULL,
  description TEXT NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_module (module, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Item Categories (user-defined; used by Item Adder)
-- code auto: 01, 02, 03… (2 digits, numeric only)
-- ------------------------------------------------------------
CREATE TABLE item_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(10)  NOT NULL UNIQUE,
  title      VARCHAR(150) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_itemcat_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Items Master (New Item Adder)
-- item_code auto under category: category(2) + serial(4) e.g. 010001
-- No picture field — not required
-- stock / rates managed here; purchase form will fetch later
-- ------------------------------------------------------------
CREATE TABLE items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id     INT UNSIGNED NOT NULL,
  item_code       VARCHAR(20)  NOT NULL UNIQUE,
  item_name       VARCHAR(200) NOT NULL,
  packing         VARCHAR(80)  NULL COMMENT 'Unit / packing e.g. Bag, Pcs, Box',
  sale_rate       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  purchase_rate   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  stock           DECIMAL(18,3) NOT NULL DEFAULT 0.000,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_items_category (category_id),
  KEY idx_items_name (item_name),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES item_categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_items_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Purchase Form (master + item lines)
-- invoice_no auto 1,2,3… per FY · sequence shared with CRV/CPV/JV
-- bill_no user-entered · cash/credit · party + company
-- Lines: item, packing, batch, qty, rate, double discount → net
-- Footer: total + bill expense → final net
-- ------------------------------------------------------------
CREATE TABLE purchases (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  financial_year_id  INT UNSIGNED NOT NULL,
  invoice_no         INT UNSIGNED NOT NULL,
  voucher_ref        VARCHAR(30) NOT NULL,
  purchase_date      DATE NOT NULL,
  bill_no            VARCHAR(50) NULL,
  pay_mode           ENUM('Cash','Credit') NOT NULL DEFAULT 'Credit',
  party_id           INT UNSIGNED NOT NULL COMMENT 'Supplier subsidiary head',
  company_name       VARCHAR(150) NULL,
  cash_account_id    INT UNSIGNED NULL COMMENT 'Required when pay_mode=Cash',
  subtotal           DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of line net amounts',
  bill_expense       DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_amount         DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'subtotal + bill_expense',
  narration          VARCHAR(500) NULL,
  status             ENUM('posted','void') NOT NULL DEFAULT 'posted',
  created_by         INT UNSIGNED NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pur_fy_inv (financial_year_id, invoice_no),
  UNIQUE KEY uq_pur_ref (voucher_ref),
  KEY idx_pur_party (party_id),
  KEY idx_pur_date (purchase_date),
  CONSTRAINT fk_pur_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id),
  CONSTRAINT fk_pur_party FOREIGN KEY (party_id) REFERENCES subsidiary_heads(id),
  CONSTRAINT fk_pur_cash FOREIGN KEY (cash_account_id) REFERENCES subsidiary_heads(id),
  CONSTRAINT fk_pur_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_details (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id       INT UNSIGNED NOT NULL,
  line_no           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  item_id           INT UNSIGNED NOT NULL,
  item_code         VARCHAR(20) NOT NULL,
  item_title        VARCHAR(200) NOT NULL,
  packing           VARCHAR(80) NULL,
  batch_no          VARCHAR(50) NULL,
  qty               DECIMAL(18,3) NOT NULL DEFAULT 0.000,
  rate              DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gross_amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'qty * rate',
  disc1_pct         DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  disc1_amt         DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  amount_after_d1   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  disc2_pct         DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  disc2_amt         DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_amount        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_purd_master FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_purd_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB;

-- ============================================================
-- Chart of Accounts + Items intentionally EMPTY.
-- Create heads / categories / items from the app UI.
-- ============================================================
-- Full schema includes:
--   users, settings, financial_years
--   main_heads, sub_heads, subsidiary_heads
--   voucher_sequence (shared CRV/CPV/JV sequence_no)
--   cash_receipt_vouchers + details
--   cash_payment_vouchers + details
--   journal_vouchers + details
--   item_categories, items
--   purchases + purchase_details
--   ledger, audit_trail
-- ============================================================

SELECT 'Accounting System full schema imported (empty COA + items, PUR+JV included)' AS status;
