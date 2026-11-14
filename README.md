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
| `COFFEEJI_WEBHOOK_TOKEN` | Geheimnis in der Webhook-URL, mindestens 16 Zeichen |
| `COFFEEJI_WEBHOOK_IPS` | Optional, kommagetrennte Absender-IPs |
| `COFFEEJI_WEBHOOK_MAX_BYTES` | Optionale Body-Obergrenze, Standard 65536 |

In `app/config/config.php` heißen dieselben Werte `$coffeeji_base_url`, `$coffeeji_api_key`, `$coffeeji_api_secret`, `$coffeeji_webhook_token`, `$coffeeji_webhook_ips` und `$coffeeji_webhook_max_bytes`. Platzhalter stehen in `app/config/config.example.php`. Solange die API-Schlüssel fehlen oder `CHANGE_ME` sind, zeigt das Dashboard einen Hinweis. Die Abrechnung läuft davon unabhängig. Ohne Webhook-Token antwortet `/coffeeji_webhook` mit HTTP 403.

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

Neue Tabellen: `cj_devices`, `cj_materials`, `cj_material_levels`, `cj_orders` (`order_no` eindeutig), `cj_sync_runs`, `cj_webhook_log`. Geräte- und Bestell-IDs sind `VARCHAR`, weil die API 19-stellige Kennungen liefert. `cj_webhook_log` ist die frühere Vorbereitung und bleibt ungenutzt. Der Empfänger schreibt nach `cj_webhook_events` aus der zweiten Migration:

```bash
mysql -u abrechnung -p abrechnung < app/db/migrations/20261114_coffeeji_webhook.sql
```

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

## Webhook

Coffeeji schickt drei Ereignisse per POST und JSON. Eine Signatur ist in der Doku nicht beschrieben. Der Empfang ist deshalb nur offen, wenn `$coffeeji_webhook_token` gesetzt ist. Erzeugen:

```bash
openssl rand -hex 32
```

Dieselbe Adresse kommt in alle drei URL-Felder im Händlerportal unter Benutzerinfo, Tab **open api** (Geräte-Störung, Material, Bestellungen), inklusive Schalter „enable“:

- Test: `https://test.thehendricks-coffee.de/coffeeji_webhook?token=…`
- Live, erst nach Freigabe: `https://abrechnung.thehendricks-coffee.de/coffeeji_webhook?token=…`

Der Endpunkt verlangt kein Login. Er erkennt den Typ an den Feldern (`deviceRunStatus` und `exceptionRemark`, `deviceScarceStatus` und `scarceRemark`, `orderNo` und `orderStatus`). Jedes Ereignis landet roh in `cj_webhook_events` (Typ, Gerät, Bestellnummer, JSON, Empfangszeit in UTC, Status, SHA-256 zur Duplikaterkennung). `devicePwd` wird vor dem Speichern und vor dem Log entfernt. Danach werden Gerätelaufstatus, Mangelstatus oder der Bestellstatus über `orderNo` idempotent nachgezogen. Fehlende Bestelldetails holt weiter der Cron. Füllstandszahlen holt weiter der Material-Abruf.

Webhook-Status 7 bedeutet erstattet und storniert. In der Bestellliste bedeutet Status 7 „nicht hergestellt“. Der Empfänger schreibt Webhook-7 deshalb als Status 0 mit `refund_status` 1. Der Rohwert 7 bleibt im Payload. Der nächste Bestell-Cron überschreibt `cj_orders.status` mit dem Listenstatus.

Die angenommene Antwort ist immer genau `{"code":200,"success":true,"data":true,"msg":"Success"}`, auch bei einem Duplikat und wenn die nachgelagerte Übernahme in `cj_*` fehlschlägt. In dem Fall bleibt das Ereignis mit Status `error` gespeichert und steht im Log `storage/logs/coffeeji.log`. Ein fehlendes oder falsches Token sowie eine nicht gelistete Absender-IP ergeben HTTP 403, ohne dass etwas gespeichert wird. Die optionale IP-Liste prüft `REMOTE_ADDR`, also die Adresse, die PHP sieht. Leer lassen, solange die Absender von Coffeeji nicht bekannt sind.

ModSecurity, eine WAF oder zusätzliche `.htaccess`-Regeln dürfen POST auf `/coffeeji_webhook` nicht blockieren. Das Zugangslog des Webservers enthält das Token in der URL.

Dashboard und Maschinen zeigen bei einer laufenden Störung oder einem Materialalarm die Zeit des letzten passenden Webhooks.

## Ausspielen

Zuerst auf einer Test-Subdomain, nie als erster Schritt auf der laufenden Abrechnung.

1. Backup der Datenbank und des bisherigen Verzeichnisses.
2. Code ausrollen, `app/config/config.php` nicht überschreiben.
3. `composer install --no-dev` im Projektverzeichnis.
4. Beide Coffeeji-Migrationen einspielen.
5. Coffeeji-Schlüssel setzen, das Webhook-Token erzeugen und die Cron-Zeilen erst nach einem manuellen Probelauf (`php app/cli/coffeeji_sync.php devices`) eintragen.
6. Die Webhook-URL im Coffeeji-Portal eintragen, nachdem ein POST gegen `/coffeeji_webhook` die Erfolgshülle liefert.
7. Anmeldung, Dashboard, eine bestehende Abrechnungsseite und ein Excel-Upload prüfen.

## Tests

```bash
composer install
php -l index.php
php -l coffeeji_webhook.php
find app tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
```

Die Tests nutzen erfundene Beispielantworten. Echte Umsätze, Bestellungen oder Gerätekennungen gehören nicht ins Repository.
