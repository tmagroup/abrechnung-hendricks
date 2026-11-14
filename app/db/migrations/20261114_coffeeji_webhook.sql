-- Empfang der Coffeeji-Webhooks (Geräte-Störung, Material-Alarm, Bestell-Push).
-- received_at und processed_at sind UTC.
-- devicePwd wird nicht gespeichert; der Empfänger entfernt das Feld vor dem Schreiben.
-- Webhook-Status 7 (erstattet/storniert) bleibt im Payload. In cj_orders wird er
-- nicht als Listenstatus 7 ("nicht hergestellt") übernommen.

CREATE TABLE IF NOT EXISTS cj_webhook_events (
	id BIGINT NOT NULL AUTO_INCREMENT,
	event_type VARCHAR(32) NOT NULL,
	device_id VARCHAR(32) NULL,
	device_no VARCHAR(64) NULL,
	order_no VARCHAR(80) NULL,
	payload_json LONGTEXT NOT NULL,
	payload_hash CHAR(64) NOT NULL,
	received_at DATETIME NOT NULL COMMENT 'UTC',
	processed_status VARCHAR(32) NOT NULL,
	processed_at DATETIME NULL COMMENT 'UTC',
	error_message VARCHAR(255) NULL,
	PRIMARY KEY (id),
	KEY idx_cj_webhook_events_hash (payload_hash, processed_status),
	KEY idx_cj_webhook_events_device (device_id, id),
	KEY idx_cj_webhook_events_order (order_no),
	KEY idx_cj_webhook_events_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
