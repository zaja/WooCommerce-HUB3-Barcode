# WooCommerce HUB3 Barcode

Plugin za WooCommerce koji generira PDF417 2D barkod prema **HUB3 standardu** za brzo plaćanje putem mobilnog bankarstva.

## Opis

Plugin automatski generira HUB3 barkod za narudžbe plaćene putem BACS (Izravni bankovni prijenos) metode plaćanja. Kupac može skenirati barkod mobilnom aplikacijom svoje banke i automatski popuniti sve podatke za plaćanje.

### Gdje se prikazuje barkod

- **Stranica zahvale (Thank You page)** - nakon završetka narudžbe
- **Pregled narudžbe** - u korisničkom računu (Moj račun → Narudžbe → Pregled)
- **Email kupcu** - u emailovima: On-Hold, Processing, Invoice
- **Admin panel** - na stranici pojedine narudžbe

Download: https://github.com/zaja/WooCommerce-HUB3-Barcode/archive/refs/heads/main.zip

<img width="1280" height="896" alt="image" src="https://github.com/user-attachments/assets/209145c1-f2dd-4d07-bb84-3cc77c74ce5b" />

## Zahtjevi

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- BACS metoda plaćanja mora biti omogućena

## Instalacija

1. Učitajte folder `woo-hub3-barcode` u `/wp-content/plugins/`
2. Aktivirajte plugin kroz 'Dodaci' izbornik u WordPressu
3. Idite na **WooCommerce → Postavke → HUB3 Barcode**
4. Unesite podatke o primatelju (IBAN, naziv, adresa)
5. Spremite postavke

## Konfiguracija

Postavke se nalaze u **WooCommerce → Postavke → HUB3 Barcode**.

### Opće postavke

| Postavka | Opis |
|----------|------|
| Tekst iznad barkoda | Tekst koji se prikazuje iznad barkoda |

### Podaci o primatelju

| Postavka | Opis |
|----------|------|
| Primatelj | Naziv tvrtke ili ime primatelja |
| Adresa | Ulica i kućni broj |
| Poštanski broj | Poštanski broj |
| Mjesto | Grad/mjesto |
| IBAN | IBAN račun primatelja (bez razmaka) |

### Postavke plaćanja

| Postavka | Opis |
|----------|------|
| Model plaćanja | HR99, HR00, HR01, HR02, HR03, HR04 |
| Poziv na broj - format | Način generiranja poziva na broj |
| Oblik datuma | Format datuma u pozivu na broj |
| Prefiks poziva na broj | Tekst ispred poziva na broj |
| Sufiks poziva na broj | Tekst iza poziva na broj |
| Šifra namjene | OTHR, GDDS, SCVE, itd. ili vlastita šifra |
| Opis plaćanja | Opis koji će se pojaviti na izvodu (podržava {order_number}, {order_date}) |

### Modeli plaćanja

- **HR99** - Bez kontrole (najčešće korišten)
- **HR00** - Bez poziva na broj
- **HR01** - Jedan broj (P1)
- **HR02** - Dva broja (P1-P2)
- **HR03** - Tri broja (P1-P2-P3)
- **HR04** - Jedan broj (P1)

### Formati poziva na broj

- Samo broj narudžbe (npr. `2824`)
- Broj narudžbe - Datum (npr. `2824-31012026`)
- Datum - Broj narudžbe (npr. `31012026-2824`)
- Samo datum (npr. `31012026`)

## Ažuriranje PDF417 biblioteke

Plugin koristi `tecnickcom/tc-lib-barcode` biblioteku za generiranje PDF417 barkoda.

### Koraci za ažuriranje:

1. **Povežite se na server** putem SSH ili terminala

2. **Navigirajte do plugin foldera:**
   ```bash
   cd /wp-content/plugins/woo-hub3-barcode
   ```

3. **Pokrenite Composer update:**
   ```bash
   composer update tecnickcom/tc-lib-barcode
   ```

4. **Alternativno - potpuna reinstalacija:**
   ```bash
   rm -rf vendor
   composer install
   ```

### Provjera verzije

Trenutno instalirana verzija biblioteke može se vidjeti u `composer.lock` datoteci ili pokretanjem:
```bash
composer show tecnickcom/tc-lib-barcode
```

## Struktura datoteka

```
woo-hub3-barcode/
├── woo-hub3-barcode.php      # Glavni plugin file
├── composer.json             # Composer konfiguracija
├── composer.lock             # Zaključane verzije paketa
├── README.md                 # Ova dokumentacija
├── includes/
│   ├── class-hub3-data.php   # HUB3 data generator
│   └── class-pdf417-generator.php  # PDF417 barcode generator
├── assets/
│   └── css/
│       ├── admin.css         # Admin stilovi
│       └── frontend.css      # Frontend stilovi
└── vendor/                   # Composer dependencies (NE BRISATI!)
    ├── autoload.php
    ├── composer/
    └── tecnickcom/
        ├── tc-lib-barcode/   # PDF417 biblioteka
        └── tc-lib-color/     # Dependency
```

## HUB3 Standard

HUB3 je hrvatski standard za 2D barkod na uplatnicama. Sadrži sve podatke potrebne za plaćanje:

- Podaci o platitelju (ime, adresa)
- Podaci o primatelju (ime, adresa, IBAN)
- Iznos i valuta
- Model i poziv na broj
- Šifra namjene
- Opis plaćanja

Više informacija: [HUB3 specifikacija](https://www.hub.hr/hr/hub3-standard)

## Rješavanje problema

### Barkod se ne generira

1. Provjerite je li `vendor/` folder prisutan
2. Provjerite PHP error log za greške
3. Provjerite je li BACS metoda plaćanja omogućena

### Barkod se ne prikazuje na Thank You stranici

1. Provjerite je li narudžba plaćena BACS metodom
2. Provjerite nije li narudžba već označena kao plaćena

### Banka ne prepoznaje barkod

1. Provjerite IBAN - mora biti točan i bez razmaka
2. Provjerite model plaćanja - HR99 ili HR00 je najsigurniji izbor
3. Provjerite da poziv na broj sadrži samo brojeve i crtice

## Changelog

### 1.0.0
- Inicijalna verzija
- PDF417 generiranje prema HUB3 standardu
- Prikaz na Thank You stranici, narudžbi i emailu
- Admin postavke u WooCommerce Settings
- Podrška za HPOS (High-Performance Order Storage)

## Autor

**Goran Zajec**  
https://svejedobro.hr

## Licenca

GPL v2 ili novija
