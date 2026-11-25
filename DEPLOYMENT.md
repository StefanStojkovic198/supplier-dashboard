# SupplyHub — postavljanje i provera aplikacije

Ovaj dokument opisuje zahteve za pokretanje SupplyHub-a na PHP hostingu i osnovne provere nakon objavljivanja.

## Serverski zahtevi

- PHP 8.1+ sa ekstenzijama SimpleXML, libxml, JSON i session.
- Apache 2.4+ sa omogućenim `.htaccess` pravilima ili odgovarajuća konfiguracija drugog web-servera.
- HTTPS za javno dostupnu aplikaciju.
- Dozvola za PHP upis u `config/` i `data/`, bez korišćenja dozvola `777`.
- Administratorska lozinka postavljena na serveru kao `SUPPLYHUB_ADMIN_PASSWORD` (najmanje 16 znakova). Lozinku ne čuvati u Git repozitorijumu.

## Datoteke za objavljivanje

Na server se postavljaju:

```text
supplyhub/
├── .htaccess
├── index.php
├── download.php
├── save-settings.php
├── assets/
├── includes/          # PHP obrada i zaštitni .htaccess
├── config/            # settings.json i zaštitni .htaccess
└── data/              # tri ulazna XML kataloga, izvoz.xml i .htaccess
```

Razvojni direktorijumi `dev/` i `tests/`, `.git/`, lokalni logovi i privremene datoteke ne postavljaju se na javni server. Na Apache serveru interni direktorijumi su zaštićeni pravilima u svojim `.htaccess` datotekama. Za Nginx je neophodno zasebno podesiti ekvivalentna pravila.

## Provera nakon objavljivanja

Sledeće putanje odnose se na osnovni URL aplikacije (npr. `/supplyhub/`):

| Putanja | Očekivani odgovor |
|---|---|
| `/` | 200 — prikaz dashboarda |
| `/download.php` | 200 — preuzimanje `izvoz.xml` |
| `/save-settings.php` (GET) | 405 — metod nije dozvoljen |
| `/config/settings.json` | 403 — pristup zabranjen |
| `/data/izvoz.xml` | 403 — direktan pristup zabranjen |
| `/includes/products.php` | 403 — pristup zabranjen |

Proveriti učitavanje kataloga, pretragu po nazivu i EAN-u, filter dobavljača, paginaciju i preuzimanje XML-a. Pri početnim parametrima (**Alfa 5%, Beta 7%, Gama 0%, PDV 20%**) katalog sadrži **52 jedinstvena proizvoda**.

Za proveru obračuna privremeno promeniti maržu kroz panel i sačuvati podešavanja uz administratorsku lozinku. Potvrditi promenu cena i ponovno generisanje `data/izvoz.xml`, zatim vratiti početne parametre. Zahtev sa pogrešnom lozinkom ne sme izmeniti podešavanja.

## Napomene o pristupu

`data/izvoz.xml` nije direktno dostupan preko URL-a; aplikacija ga isporučuje kroz `download.php`. Pošto izvozni katalog sadrži i nabavne cene, pristup preuzimanju treba dodatno ograničiti ako ti podaci nisu namenjeni javnom prikazu.

Javni demo biće dostupan najmanje 14 dana nakon predaje. GitHub Pages nije odgovarajući hosting za ovaj projekat jer ne izvršava PHP.
