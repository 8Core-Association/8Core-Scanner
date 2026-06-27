# 8Core Scanner — CHANGELOG

## v1.5 — 2026-06-27

### Novo
- **Admin sučelje** (`admin/index.php`) — zasebna admin homepage s pregledom statistika (Critical/High/neobrađeni/ukupno nalaza, aktivni korisnici, broj accounta) i brzim akcijama
- **Odvojeni routing** — admin se nakon logina preusmjerava na `admin/index.php`, korisnik direktno na Scanner (`index.php`)
- **Višestruki accounti po korisniku** — nova tablica `scanner_user_accounts (user_id, account_name)` zamjenjuje jedan `account_name` stupac; jedan korisnik može imati N accounta
- **Multi-account UI** (`admin/users.php`) — checkboxovi za dodjelu/oduzimanje accounta pri kreiranju korisnika i inline "Uredi accounte" panel za svaki postojeći korisnički redak
- **IN-filter za scanner** — `index.php` filtrira findinge po `WHERE account_name IN (...)` za sve accounte prijavljenog korisnika
- **Sidebar** — admin u Scanneru vidi link "Admin panel" umjesto "Users"; u Admin sučelju vidljiv link "Otvori Scanner"

### Izmijenjeno
- `login.php` — redirect po roli (`is_admin()` → `admin/index.php`, inače `index.php`)
- `includes/auth.php` — novi helper `user_accounts()`, `login_user()` punjuje session s nizom accounta, `can_access_finding()` provjerava `IN` umjesto jednakosti
- `migrate.php` — dodana kreacija tablice `scanner_user_accounts IF NOT EXISTS` + automatska migracija podataka iz starog `scanner_users.account_name`

### Licencni header
- Dodan vlasnički licencni komentar na sve PHP fajlove

---

## v1.4 — inicijalna verzija

### Funkcionalnosti
- **Login sustav** — session-based auth, bcrypt lozinke, `scanner_users` tablica s rolama `admin` / `user`
- **Scanner dashboard** (`index.php`) — tablica nalaza s expand redovima, detalji fajla, SHA-256, mtime/ctime/birth
- **Filteri** — risk (CRITICAL/HIGH/MEDIUM/LOW), account, action_status, full-text pretraga
- **Akcije na nalazima** — `action.php` + `scanner_actions` audit log; statusi: `new`, `checked`, `ignore`, `quarantine_requested`, `delete_requested`
- **Admin users** (`admin/users.php`) — kreiranje korisnika, toggle active/inactive, reset lozinke
- **migrate.php** — idempotentna migracija: ADD COLUMN IF NOT EXISTS, kreacija `scanner_actions` i `scanner_users`, default admin korisnik
- **debug.php** — plain-text dijagnostika PHP verzije, PDO, tablica i stupaca
- **Bash skripte** (`ioc_scan.sh`) — shell scanner koji upisuje u MySQL, podrška za `account_name`, `relative_path`, `source_guess`, `file_ext`, `sha256`
- **CSS tema** (`scanner.css`) — dark security tema, responsive layout, risk/status badge sustav
- **JS** (`scanner.js`) — expand/collapse redovi, action dropdown, click-outside zatvaranje

---

## Struktura projekta

```
Projekt web scanner v.1.4/
├── Front wnd web - dio/          # PHP web sučelje
│   ├── index.php                 # Scanner dashboard (korisnici + admin)
│   ├── login.php                 # Prijava
│   ├── logout.php                # Odjava
│   ├── action.php                # POST handler za akcije na nalazima
│   ├── migrate.php               # DB migracija (pokrenuti jednom)
│   ├── debug.php                 # Dijagnostika okruženja
│   ├── admin/
│   │   ├── index.php             # Admin homepage (statistike + navigacija)
│   │   └── users.php             # Upravljanje korisnicima + dodjela accounta
│   ├── includes/
│   │   ├── auth.php              # Session auth, helper funkcije
│   │   ├── config.php            # DB kredencijali, default admin
│   │   ├── db.php                # PDO konekcija
│   │   └── helpers.php           # h(), risk_class(), action_class(), flash_message()
│   └── assets/
│       ├── css/scanner.css       # Stilovi
│       └── js/scanner.js         # Frontend logika
└── Skripte van weba _ root/      # Shell skripte (izvršavaju se na serveru kao root)
    ├── ioc_scan.sh               # Glavni IOC scanner
    ├── scanner-db.conf           # DB konfiguracija za shell skripte
    ├── ioc-scan-live.log         # Live log zadnjeg scana
    └── ioc-debug.log             # Debug log
```

## Pokretanje / deployment

1. Postavi `includes/config.php` s DB kredencijalima
2. Otvori `migrate.php` u browseru — kreira sve tablice i default admin korisnika
3. Promijeni default admin lozinku pri prvom loginu
4. Konfiguracija bash skripte: uredi `Skripte van weba _ root/scanner-db.conf`
5. Postavi cron za `ioc_scan.sh` (preporučeno: root cron, svakih N sati)

## Vlasništvo

(c) 2026 Tomislav Galić — 8Core d.o.o.
Web: https://8core.hr | info@8core.hr | +385 099 851 0717
Sva prava pridržana. Zabranjeno distribuiranje i mijenjanje bez dopuštenja.
