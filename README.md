# SupplyHub — objedinjavanje ponuda dobavljača

Full Stack web aplikacija koja učitava tri dobavljačka XML kataloga, obračunava prodajne cene na **PHP serveru**, bira najpovoljniju ponudu po EAN kodu, generiše `izvoz.xml` i prikazuje objedinjeni katalog u responsive dashboardu.

## Funkcionalnosti

- Čitanje `alfa_distribucija.xml`, `beta_trade.xml` i `gama_import.xml` (`includes/products.php`).
- Prodajna cena: **nabavna bez PDV-a × (1 + marža / 100) × (1 + PDV / 100)**. Obračun je u celobrojnim parama, sa zaokruživanjem posle marže i posle PDV-a (`includes/pricing.php`).
- Zarada: **prodajna cena bez PDV-a − nabavna cena**. PDV nije zarada.
- Jedan proizvod po EAN-u: izabran je dobavljač sa najnižom **prodajnom cenom sa PDV-om**. U slučaju jednake cene prednost ima veći lager, potom ID dobavljača i SKU (`includes/merge.php`).
- Generisanje i čuvanje `data/izvoz.xml` sa EAN-om, nazivom, kategorijom, brendom, dobavljačem, lagerom, nabavnom/prodajnom cenom, zaradom i svim URL-ovima slika iz izabrane ponude (`includes/export.php`).
- Prikaz proizvoda, pretraga po nazivu/EAN-u, kombinovani filter po dobavljaču, paginacija, statistika i preuzimanje XML-a (`index.php`, `assets/`, `includes/catalog.php`, `download.php`).
- Podešavanje marži i PDV-a bez izmene koda: PHP čuva `config/settings.json`, ponovo obračunava ponude i regeneriše XML (`includes/settings.php`, `save-settings.php`).

## Tehnologije i zahtevi

- PHP **8.1+** i ekstenzije **SimpleXML** (za XML), `json`, `session`, `libxml`.
- Apache **2.4+** sa omogućenim `.htaccess` pravilima za bezbedno serviranje (npr. MAMP). Za Nginx su potrebna ekvivalentna pravila — `.htaccess` tamo ne radi.
- HTML, CSS, JavaScript bez dodatnog frameworka i bez baze podataka.
- PHP procesu treba pristup za **čitanje** ulaznih XML fajlova, i **upis** u `config/` i `data/` prilikom menjanja podešavanja. Ne koristiti dozvole `777`.

## Lokalno pokretanje (MAMP)

1. Kopiraj projekat u `/Applications/MAMP/htdocs/supplier-dashboard/`.
2. U MAMP-u pokreni Apache/PHP. Otvori `http://localhost:8888/supplier-dashboard/` (ako koristiš drugi port, prilagodi URL).
3. Početne marže iz `config/settings.json` su **Alfa 5%**, **Beta 7%**, **Gama 0%** i **PDV 20%**.
4. Tabela koristi već generisani `data/izvoz.xml`. Za promenu parametara otvori **Podešavanja**, izmeni procente, klikni **Sačuvaj i preračunaj**. Promena ponovo obrađuje sva tri ulazna fajla i ažurira XML.
5. Klikni **Preuzmi XML** da dobiješ `izvoz.xml`. Namerno se preuzima kroz `download.php`, dok direktan HTTP pristup folderu `data/` treba da bude odbijen.

## Struktura

```text
supplier-dashboard/
├── index.php               # Dashboard
├── download.php            # Javno preuzimanje izvoz.xml
├── save-settings.php       # POST API za promenu marži/PDV-a
├── assets/
│   ├── app.js              # Pretraga, filtriranje, prikaz, forma
│   └── style.css           # Responsive dark UI
├── includes/
│   ├── products.php        # XML ulaz
│   ├── pricing.php         # PHP obračun
│   ├── merge.php           # Izbor dobavljača po EAN-u
│   ├── export.php          # XML izvoz
│   ├── catalog.php         # Čitanje sačuvanog izvoza za UI
│   └── settings.php        # Validacija, čuvanje i ponovni obračun
├── config/
│   └── settings.json       # Podesive marže i PDV
└── data/
    ├── alfa_distribucija.xml
    ├── beta_trade.xml
    ├── gama_import.xml
    └── izvoz.xml
```

Postoje i `.htaccess` fajlovi koji štite interne foldere, kao i `.gitignore`. Razvojni `dev/` folder služi samo za lokalno testiranje i **ne sme da bude objavljen na javnom serveru**.

## Pravila za neispravne podatke

U dostavljenim XML fajlovima postoje rubni slučajevi. U konačnom automatskom izboru preskaču se ponude sa **praznim/neispravnim EAN-om, nabavnom cenom 0 RSD ili negativnim lagerom**. **Lager 0 nije razlog za preskakanje**, jer zadatak ne zahteva isključivanje nedostupne robe. Originalni XML fajlovi ostaju neizmenjeni. Ova pravila su odluka implementacije, nisu posebno propisana zadatkom.

Na početnoj konfiguraciji testirano je: **122 ulazne ponude, 118 validnih, 52 jedinstvena proizvoda**, od čega Alfa 14, Beta 9, Gama 29. Promenom marži ovi rezultati po dobavljaču mogu da se promene.

Linkovi za slike iz ulaznih XML kataloga vode ka `picsum.photos` (ilustrativne/testne fotografije). Aplikacija prenosi URL-ove originalne izabrane ponude; ne tvrdi da su fotografije stvarni proizvodi.

## Bezbednost

- Na `localhost` je upis podešavanja dozvoljen radi lokalnog razvoja.
- Na **javnom serveru** promena podešavanja zahteva **HTTPS** i server-side env promenljivu `SUPPLYHUB_ADMIN_PASSWORD` sa jedinstvenom lozinkom dužine **najmanje 16 znakova**. Lozinka se **ne stavlja u Git, README, JS, JSON, niti u `.htaccess` ako će taj fajl biti commitovan**.
- `save-settings.php` prihvata samo POST, koristi CSRF token, validaciju i proveru verzije podešavanja.
- `.htaccess` pravila Apache-a moraju blokirati direktan pristup `config/`, `data/`, `includes/`, i skrivenim/metapodacima. Pre produkcije ukloniti `dev/` i `tests/`.
- Ako hosting koristi Nginx, mora se posebno podesiti zabrana pristupa internim folderima.
- PHP errors na javnom serveru treba logovati, ne prikazivati korisnicima.

## Priprema za produkciju / predaja

Detaljan postupak i kontrolni testovi su u [DEPLOYMENT.md](DEPLOYMENT.md).

**Predaja:** link ka javno dostupnoj PHP aplikaciji, Git repozitorijum sa istorijom promena, README sa lokalnim koracima i generisani `data/izvoz.xml`. Aplikacija treba da ostane dostupna najmanje **14 dana od predaje**.
