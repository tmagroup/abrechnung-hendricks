# Hendricks Coffee CRM

Abrechnung für Hendricks Coffee, ergänzt um eine Live-Übersicht aus der Coffeeji Merchant Open API. Die Oberfläche folgt dem freigegebenen Entwurf B1. Die bestehende Abrechnung (Excel-Upload, Detailabrechnung, Abrechnung gesamt, Provisionsabrechnung, Provisionsempfänger, Konto, Anmeldung) rechnet unverändert; geändert ist dort nur die Optik.

Technik: PHP 8.2, AdminLTE, jQuery, MySQL. Kein Framework.

## Coffeeji einrichten

Schlüssel nur in `app/config/config.php` (liegt in `.gitignore`) oder als Umgebungsvariablen. Die Umgebungsvariablen haben Vorrang.

| Schlüssel | Bedeutung |
| --- | --- |
| `COFFEEJI_BASE_URL` | Standard `https://vdopen.coffeeji.com` |
| `COFFEEJI_API_KEY` | API-Key aus dem Händlerportal |
| `COFFEEJI_API_SECRET` | API-Secret aus dem Händlerportal |

In `app/config/config.php` heißen dieselben Werte `$coffeeji_base_url`, `$coffeeji_api_key` und `$coffeeji_api_secret`. Platzhalter stehen in `app/config/config.example.php`. Solange die Schlüssel fehlen oder `CHANGE_ME` sind, zeigt das Dashboard einen Hinweis. Die Abrechnung läuft davon unabhängig.

Der Client liest nur:

- `POST /coffee/newThird/device/page`
- `GET /coffee/newThird/device/detail/{id}`
- `GET /coffee/newThird/material/{deviceId}`
- `GET /coffee/newThird/order/getOrderList` (Query-Parameter, nicht POST)
- `POST /coffee/newThird/goods/list`
- `POST /coffee/newThird/goods/deviceGoodsList`

Schreibende Endpunkte sind im Client nicht vorhanden. Das Feld `devicePwd` wird vor dem Speichern und vor der Anzeige entfernt und nicht geloggt.

## Datenbank

Einmalig auf der Zieldatenbank einspielen:

```bash
mysql -u abrechnung -p abrechnung < app/db/migrations/20261113_coffeeji.sql
```

Neue Tabellen: `cj_devices`, `cj_materials`, `cj_material_levels`, `cj_orders` (`order_no` eindeutig), `cj_sync_runs`, `cj_webhook_log`. Geräte- und Bestell-IDs sind `VARCHAR`, weil die API 19-stellige Kennungen liefert. Die Webhook-Tabelle ist nur vorbereitet; ein Empfänger ist noch nicht aktiv.

Danach in der bestehenden `config.php` die Startseite umstellen, wenn das Dashboard die Einstiegsseite sein soll:

```php
$default_file = 'dashboard.php';
```

Nach der Anmeldung führt die Anwendung bereits auf das Dashboard.

## Abruf per Cron

Nichts davon wird vom Repository eingerichtet. `/PFAD` durch das Verzeichnis ersetzen, in dem `index.php` liegt. PHP-CLI 8.2.

```cron
*/7 * * * * php /PFAD/app/cli/coffeeji_sync.php devices >> /PFAD/storage/logs/coffeeji-cron.log 2>&1
*/10 * * * * php /PFAD/app/cli/coffeeji_sync.php materials >> /PFAD/storage/logs/coffeeji-cron.log 2>&1
*/2 * * * * php /PFAD/app/cli/coffeeji_sync.php orders >> /PFAD/storage/logs/coffeeji-cron.log 2>&1
20 3 * * * php /PFAD/app/cli/coffeeji_sync.php orders-backfill >> /PFAD/storage/logs/coffeeji-cron.log 2>&1
```

- `devices` und `materials`: alle 7 bzw. 10 Minuten.
- `orders`: heute und gestern, Upsert über `order_no`. Der Datumsfilter der API reicht in den Vorabend hinein, deshalb filtert der Sync das Fenster lokal in Europe/Berlin nach.
- `orders-backfill`: nächtlicher Abgleich der letzten 35 Tage.

Umsatz auf den neuen Seiten zählt nur Bestellungen mit Status 2 (ausgegeben). Beträge erscheinen als `1.234,50 €`, Uhrzeiten in MEZ.

## Ausspielen

Zuerst auf einer Test-Subdomain, nie als erster Schritt auf der laufenden Abrechnung.

1. Backup der Datenbank und des bisherigen Verzeichnisses.
2. Code ausrollen, `app/config/config.php` nicht überschreiben.
3. `composer install --no-dev` im Projektverzeichnis.
4. Migration einspielen.
5. Coffeeji-Schlüssel setzen und die Cron-Zeilen erst nach einem manuellen Probelauf (`php app/cli/coffeeji_sync.php devices`) eintragen.
6. Anmeldung, Dashboard, eine bestehende Abrechnungsseite und ein Excel-Upload prüfen.

## Tests

```bash
composer install
php -l index.php
find app tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
```

Die Tests nutzen erfundene Beispielantworten. Echte Umsätze, Bestellungen oder Gerätekennungen gehören nicht ins Repository.
