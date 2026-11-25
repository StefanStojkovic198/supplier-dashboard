# SupplyHub — bezbednosna provera i postavljanje online

## 1. Pre hostinga: lokalni test

Iz terminala pokreni skriptu `lokalni-test/PROVERA-PRISTUPA.sh` iz Korak 16 paketa ili ručno otvori sledeće adrese u browseru:

| URL putanja | Očekivanje (Apache) |
|---|---|
| `/supplier-dashboard/` | 200 — dashboard (52 proizvoda uz početne marže) |
| `/supplier-dashboard/download.php` | 200 — preuzimanje `izvoz.xml` |
| `/supplier-dashboard/save-settings.php` | 405 — GET nije dozvoljen |
| `/supplier-dashboard/config/settings.json` | 403 — zabranjeno |
| `/supplier-dashboard/data/izvoz.xml` | 403 — zabranjeno direktno otvaranje |
| `/supplier-dashboard/includes/products.php` | 403 — zabranjeno |
| `/supplier-dashboard/dev/check-xml.php` | 403 — privremeno blokirano (ako `dev/` postoji) |

Za stranice koje su uklonjene (npr. `dev/` u produkciji), 404 je takođe prihvatljiv. Ako je bilo koja interna datoteka dostupna sa HTTP 200, **ne objavljuj aplikaciju dok to ne rešiš**.

U dashboardu potvrdi pretragu naziva i EAN-a, kombinovani filter dobavljača, stranicu 2 paginacije, preuzimanje XML-a, kao i promenu Gama marže 0 → 10 → 0 uz trajno čuvanje i ponovni obračun. Git commit ide tek posle testiranja.

## 2. Bezbedan produkcioni paket

Ne otpremati `dev/`, `tests/`, `.git/`, lokalne logove, skrinšotove i privremene fajlove. Upload samo potrebnih PHP/HTML/CSS/JS i XML fajlova. **Ne brisati** tri ulazna XML fajla, `data/izvoz.xml`, `config/settings.json` ili interne `.htaccess` zaštite.

Ako koristiš Apache 2.4, proveri da hosting prihvata `.htaccess` pravila (`AllowOverride` podešavanje). Ako koristi Nginx, samostalno pripremi Nginx pravila ili stavi interne fajlove izvan javnog `document root` foldera. Koristi HTTPS sertifikat.

PHP ekstenzije: SimpleXML, libxml, JSON, session; PHP >= 8.1. PHP procesu daj pravo upisa u `config/` i `data/`, bez `777`.

**Na javnom serveru `save-settings.php` ne dozvoljava promenu bez HTTPS i jake administratorske lozinke u env promenljivoj `SUPPLYHUB_ADMIN_PASSWORD`.** Lozinku postavi kroz server/hosting konfiguraciju **van Git repozitorijuma**. Ako hosting ne omogućava bezbedno postavljanje environment promenljive, nemoj zaobilaziti proveru; upotrebi odgovarajući hosting ili zatraži administrativnu konfiguraciju servera.

Ako je HTTPS iza reverse proxy-ja, treba ispravno proslediti HTTPS stanje PHP aplikaciji; ne isključuj sigurnosnu proveru.

## 3. Provere nakon objave

1. Otvori javni link sa telefona i desktopa. Proveri da ne prikazuje PHP greške.
2. Otvori `download.php` i proveri da preuzima `izvoz.xml` sa 52 stavke (pri početnim maržama).
3. Testiraj direktne putanje iz gornje tabele na **javnom domenu** (bez prijave): konfiguracija i interna obrada nikad ne smeju biti dostupne.
4. Potvrdi da su marže/PDV podesive na serveru preko HTTPS i lozinke. Testiraj odjavu/ponovno učitavanje.
5. Proveri da javni URL ostane aktivan najmanje 14 dana nakon predaje. Ne oslanjaj se na GitHub Pages — PHP ne izvršava na GitHub Pages.

## 4. Predaja poslodavcu

Mejl treba da sadrži: **javni URL aplikacije, URL GitHub repozitorijuma / pristup privatnom repozitorijumu, kratko uputstvo za lokalni start, i napomenu gde se nalazi `data/izvoz.xml`**. Proveri da je poslednji commit poslat (`Push origin` → `Fetch origin`). Nije dovoljno da GitHub sadrži samo kod: aplikacija mora raditi na PHP hostingu.
