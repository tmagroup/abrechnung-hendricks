-- Coffeeji-Lesedaten für das Hendricks-CRM.
-- IDs bleiben VARCHAR, weil die API 19-stellige Zeichenketten liefert.
-- devicePwd wird nicht gespeichert.
-- Einspielen auf der Zieldatenbank, danach den Sync per Cron starten.

CREATE TABLE IF NOT EXISTS cj_devices (
	id VARCHAR(32) NOT NULL,
	device_no VARCHAR(64) NULL,
	device_ext_no VARCHAR(64) NULL,
	name VARCHAR(255) NOT NULL DEFAULT '',
	status TINYINT NULL,
	device_run_status TINYINT NULL,
	exception_remark TEXT NULL,
	scarce_status TINYINT NULL,
	scarce_remark TEXT NULL,
	heart_time DATETIME NULL,
	device_type_name VARCHAR(255) NULL,
	soft_run_version VARCHAR(64) NULL,
	raw_json LONGTEXT NULL,
	synced_at DATETIME NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY idx_cj_devices_ext (device_ext_no),
	KEY idx_cj_devices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cj_materials (
	id BIGINT NOT NULL AUTO_INCREMENT,
	device_id VARCHAR(32) NOT NULL,
	material_key VARCHAR(80) NOT NULL,
	material_id VARCHAR(32) NULL,
	name VARCHAR(255) NOT NULL DEFAULT '',
	position VARCHAR(64) NULL,
	capacity DECIMAL(12,2) NOT NULL DEFAULT 0,
	residue_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
	warn_capacity DECIMAL(12,2) NOT NULL DEFAULT 0,
	unit VARCHAR(16) NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_cj_material (device_id, material_key),
	KEY idx_cj_materials_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cj_material_levels (
	id BIGINT NOT NULL AUTO_INCREMENT,
	device_id VARCHAR(32) NOT NULL,
	material_key VARCHAR(80) NOT NULL,
	name VARCHAR(255) NOT NULL DEFAULT '',
	residue_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
	capacity DECIMAL(12,2) NOT NULL DEFAULT 0,
	warn_capacity DECIMAL(12,2) NOT NULL DEFAULT 0,
	unit VARCHAR(16) NULL,
	recorded_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY idx_cj_levels_device_time (device_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cj_orders (
	id BIGINT NOT NULL AUTO_INCREMENT,
	order_no VARCHAR(80) NOT NULL,
	device_id VARCHAR(32) NULL,
	device_ext_no VARCHAR(64) NULL,
	device_name VARCHAR(255) NULL,
	goods_name VARCHAR(255) NULL,
	price DECIMAL(10,2) NOT NULL DEFAULT 0,
	pay_amt DECIMAL(10,2) NOT NULL DEFAULT 0,
	pay_way_name VARCHAR(128) NULL,
	status INT NOT NULL DEFAULT 0,
	refund_status INT NULL,
	create_time DATETIME NULL,
	time_zone_id VARCHAR(32) NULL,
	raw_json LONGTEXT NULL,
	synced_at DATETIME NULL,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_cj_orders_no (order_no),
	KEY idx_cj_orders_time (create_time),
	KEY idx_cj_orders_device_time (device_id, create_time),
	KEY idx_cj_orders_status_time (status, create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cj_sync_runs (
	id BIGINT NOT NULL AUTO_INCREMENT,
	job VARCHAR(64) NOT NULL,
	status VARCHAR(32) NOT NULL,
	message TEXT NULL,
	records_count INT NULL,
	started_at DATETIME NOT NULL,
	finished_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_cj_sync_job (job, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cj_webhook_log (
	id BIGINT NOT NULL AUTO_INCREMENT,
	event_type VARCHAR(64) NULL,
	payload MEDIUMTEXT NULL,
	received_at DATETIME NOT NULL,
	processed_at DATETIME NULL,
	PRIMARY KEY (id),
	KEY idx_cj_webhook_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
