<?php

declare(strict_types=1);

/**
 * Aus dem Abrechnungscode abgeleitetes Grundschema.
 * Produktion hat diese Tabellen bereits; die Datei legt sie nur in der Testdatenbank an.
 * Die Migration 20261121 ergänzt danach die Coffeeji-Spalten.
 */
function invoice_base_sql(): string
{
	return <<<'SQL'
CREATE TABLE IF NOT EXISTS invoice2 (
	id BIGINT NOT NULL AUTO_INCREMENT,
	start_date DATE NULL,
	end_date DATE NULL,
	excel_date VARCHAR(64) NULL,
	total_amount DECIMAL(12,2) NULL,
	created_at DATETIME NULL,
	created_by BIGINT NULL,
	deleted_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_invoice2_period (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_list2 (
	id BIGINT NOT NULL AUTO_INCREMENT,
	invoice_id BIGINT NOT NULL,
	machine_no VARCHAR(64) NULL,
	order_number VARCHAR(80) NULL,
	machine_name VARCHAR(255) NULL,
	dealer VARCHAR(255) NULL,
	goods_name VARCHAR(255) NULL,
	amount DECIMAL(12,2) NULL,
	payment_method VARCHAR(128) NULL,
	order_status VARCHAR(64) NULL,
	ship_status VARCHAR(64) NULL,
	refund_status VARCHAR(64) NULL,
	order_time DATETIME NULL,
	ship_time DATETIME NULL,
	refund_time DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_invoice_list2_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
}
