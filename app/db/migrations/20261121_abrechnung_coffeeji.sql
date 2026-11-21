-- Gesamtabrechnung aus cj_orders. Nur additive, wiederholbare Änderungen.
-- Bestehende Zeilen bleiben. Neue Spalten an invoice2 bekommen Defaults oder NULL,
-- damit alte Excel-Abrechnungen unverändert lesbar bleiben (source fällt auf excel).
-- Einspielen:
--   mysql -u abrechnung -p abrechnung < app/db/migrations/20261121_abrechnung_coffeeji.sql
--
-- Der Code setzt voraus, dass invoice2 und invoice_list2 schon existieren.
-- Es gibt im Repository kein CREATE dafür, weil das Produktionsschema nicht
-- im Repo liegt. Tests legen ein aus dem Code abgeleitetes Grundschema an
-- (tests/schema/invoice_base.php) und spielen danach diese Datei ein.
--
-- invoice2, vom Code gelesen und geschrieben:
--   id              Primärschlüssel, positiv, ganzzahlig
--   start_date      Datum JJJJ-MM-TT, Beginn inklusive
--   end_date        Datum JJJJ-MM-TT, Ende inklusive
--   excel_date      Text, bei Coffeeji der feste Wert coffeeji
--   total_amount    Brutto-Summe, Dezimal mit Punkt, zwei Nachkommastellen
--   created_at      DATETIME
--   created_by      Benutzer-ID, ganzzahlig
--   deleted_at      DATETIME NULL, weiches Löschen
--   source          excel oder coffeeji, Default excel            (neu)
--   order_count     Anzahl der Positionen                         (neu)
--   cash_amount     Bar, brutto                                   (neu)
--   card_amount     Karte, brutto                                 (neu)
--   other_amount    sonstige Zahlarten, brutto                    (neu)
--   open_count      offene Positionen zum Speicherzeitpunkt       (neu)
--   merchant        festgeschriebener Händlertext                 (neu)
--   vat_7_net       Netto 7 %, enthalten                          (neu)
--   vat_7_tax       Steuer 7 %                                    (neu)
--   vat_7_gross     Brutto 7 %                                    (neu)
--   vat_19_net      Netto 19 %                                    (neu)
--   vat_19_tax      Steuer 19 %                                   (neu)
--   vat_19_gross    Brutto 19 %                                   (neu)
--   vat_breakdown   JSON-Liste rate/net/tax/gross, festgeschrieben (neu)
--   locked_at       Zeitpunkt der Festschreibung                   (neu)
--
-- invoice_list2, vom Code gelesen und geschrieben:
--   id, invoice_id
--   machine_no, machine_name, dealer, goods_name
--   order_number    Bestellnummer (Excel-Spalte A, bei Coffeeji = order_no)
--   amount          Brutto der Position
--   payment_method  Excel: cash/card; Coffeeji: pay_way_name
--   order_status, ship_status, refund_status
--   order_time, ship_time, refund_time   DATETIME oder Text JJJJ-MM-TT hh:mm:ss
--   vat_rate        Satz der Position, festgeschrieben            (neu)
--   order_no        Bezug zur Coffeeji-Bestellung                 (neu)
--
-- cj_vat_products ist neu. Leer bedeutet: jedes Produkt 19 % und in der
-- Vorschau als nicht konfiguriert gekennzeichnet.

SET @db := DATABASE();

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'source'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT "excel"'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'order_count'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN order_count INT NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'cash_amount'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN cash_amount DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'card_amount'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN card_amount DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'other_amount'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN other_amount DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'open_count'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN open_count INT NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'merchant'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN merchant VARCHAR(255) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_7_net'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_7_net DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_7_tax'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_7_tax DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_7_gross'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_7_gross DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_19_net'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_19_net DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_19_tax'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_19_tax DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_19_gross'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_19_gross DECIMAL(12,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'vat_breakdown'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN vat_breakdown TEXT NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice2' AND COLUMN_NAME = 'locked_at'),
		'SELECT 1',
		'ALTER TABLE invoice2 ADD COLUMN locked_at DATETIME NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice_list2' AND COLUMN_NAME = 'vat_rate'),
		'SELECT 1',
		'ALTER TABLE invoice_list2 ADD COLUMN vat_rate DECIMAL(5,2) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'invoice_list2' AND COLUMN_NAME = 'order_no'),
		'SELECT 1',
		'ALTER TABLE invoice_list2 ADD COLUMN order_no VARCHAR(80) NULL'
	)
);
PREPARE abrechnung_stmt FROM @sql;
EXECUTE abrechnung_stmt;
DEALLOCATE PREPARE abrechnung_stmt;

CREATE TABLE IF NOT EXISTS cj_vat_products (
	id BIGINT NOT NULL AUTO_INCREMENT,
	goods_name VARCHAR(255) NULL,
	product_id VARCHAR(64) NULL,
	rate DECIMAL(5,2) NOT NULL,
	milk_share DECIMAL(5,2) NULL,
	milk_type VARCHAR(32) NOT NULL DEFAULT 'keine',
	evidence VARCHAR(2000) NULL,
	hot_water TINYINT NOT NULL DEFAULT 0,
	valid_from DATE NOT NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY idx_cj_vat_goods (goods_name, valid_from),
	KEY idx_cj_vat_product (product_id, valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
