# Accounting System

Professional **Accounting & Voucher Management** suite for Laragon  
(HTML · CSS · Vanilla JS · Core PHP OOP · MySQL)

---

## Features

- Premium login, curved collapsible sidebar, light/dark theme
- **Chart of Accounts** — Main → Sub → Subsidiary (auto codes)
- Opening balance / nature managed from Chart of Accounts
- **Cash Receipt (CRV)** & **Cash Payment (CPV)** — amount lines / double-entry
- **Journal Voucher (JV)** — Debit = Credit lines
- **New Item Adder** — categories + items (code, packing, sale/pur rate, stock)
- Voucher numbers: **1, 2, 3…** per type (reset each Financial Year)
- **Shared sequence** across CRV + CPV + JV · Ledger · Audit trail
- User profile + change password (show/hide on all fields)

---

## Laragon setup

1. Extract project to `C:\laragon\www\Accounting`
2. Start **Apache + MySQL**
3. Import **only** the full schema (no separate migrate files):
   - phpMyAdmin → Import → `database/schema.sql`  
   - Or CLI:
     ```bash
     mysql -u root < database/schema.sql
     ```
   - Creates / rebuilds database: **`accounting_system`**
   - Includes CRV, CPV, **JV** tables + empty Chart of Accounts
4. Open: `http://localhost/Accounting/login.php`
5. Login:
   - **Username:** `admin`
   - **Password:** `admin123`

> **Note:** Database name is `accounting_system` (config: `config/config.php`).  
> Always use `database/schema.sql` as the single source of truth — no migrate scripts.

---

## Project structure

```
Accounting/
├── app/                 Core · Models · Services (OOP PHP)
├── api/                 JSON endpoints
├── assets/css|js        UI + voucher logic
├── config/config.php    App name, DB credentials
├── database/schema.sql  Full CREATE (JV included, COA empty — always up to date)
├── includes/            Layout start/end
├── modules/
│   ├── heads/           Main · Sub · Subsidiary · Chart of Accounts
│   ├── vouchers/        CRV · CPV · List
│   └── user/            Profile · Change Password
├── index.php            Dashboard
├── login.php · logout.php
└── .htaccess
```

---

## Voucher entry (current behaviour)

| Field | Notes |
|-------|--------|
| Voucher No | Auto **1, 2, 3…** · readonly · per type per FY |
| Date | Defaults to today |
| Lines | Min **2 rows** · Account · Narration · Debit · Credit · Prev Bal |
| Save | Enabled only when **Debit = Credit** |

No fixed top cash field / header narration — everything is row-based.

---

## Default login

| Field | Value |
|-------|--------|
| Username | `admin` |
| Password | `admin123` |

---

## License

Internal / educational use.
