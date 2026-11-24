# SupplyHub — početna struktura projekta

Ovo je **korak 02: razdvajanje postojećeg demo interfejsa** u više fajlova.

- `index.php` — isti HTML dashboard kao u dostavljenom prototipu
- `assets/style.css` — izdvojeni CSS, bez redizajna
- `assets/app.js` — izdvojeni JavaScript, sa **demo** proizvodima i obračunom isključivo u pregledaču
- `data/` — tri originalna XML fajla (još nisu povezani sa aplikacijom)
- `includes/` — rezervisano za PHP obradu, sledeći korak
- `config/settings.json` — početne marže i PDV, još se ne učitavaju u aplikaciji

## Lokalno pokretanje

1. Instaliraj/pokreni MAMP ili drugi PHP lokalni server.
2. Kopiraj `supplier-dashboard/` u MAMP `htdocs/` folder.
3. Otvori `http://localhost:8888/supplier-dashboard/` ako MAMP koristi port 8888 (inače koristi port podešen u MAMP-u).

**Važno:** Ovo je samo izdvajanje postojećeg koda. Interfejs još koristi izmišljene demo proizvode; PHP parser, obračun na serveru, generisanje `izvoz.xml`, preuzimanje i trajno čuvanje podešavanja **nisu implementirani**.

`.htaccess` štiti interne foldere samo na Apache serveru. Na drugim serverima (npr. Nginx) moraju se dodati odgovarajuća pravila pristupa ili držati privatni fajlovi van javnog direktorijuma.
