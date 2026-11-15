-- Portal-Felder der Bestellliste. Idempotent, auch ein zweites Einspielen ändert nichts.
-- Quelle: getOrderList (outStockStatus, outStockTime, refundTime, failRemark, payWay).
-- Bestehende Zeilen werden aus raw_json nachgezogen, wenn die Schlüssel dort stehen.
-- Einspielen:
--   mysql -u abrechnung -p abrechnung < app/db/migrations/20261115_coffeeji_orders_portal.sql

SET @db := DATABASE();

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cj_orders' AND COLUMN_NAME = 'pay_way'),
		'SELECT 1',
		'ALTER TABLE cj_orders ADD COLUMN pay_way INT NULL AFTER pay_way_name'
	)
);
PREPARE cj_portal_stmt FROM @sql;
EXECUTE cj_portal_stmt;
DEALLOCATE PREPARE cj_portal_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cj_orders' AND COLUMN_NAME = 'out_stock_status'),
		'SELECT 1',
		'ALTER TABLE cj_orders ADD COLUMN out_stock_status INT NULL AFTER refund_status'
	)
);
PREPARE cj_portal_stmt FROM @sql;
EXECUTE cj_portal_stmt;
DEALLOCATE PREPARE cj_portal_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cj_orders' AND COLUMN_NAME = 'out_stock_time'),
		'SELECT 1',
		'ALTER TABLE cj_orders ADD COLUMN out_stock_time DATETIME NULL AFTER create_time'
	)
);
PREPARE cj_portal_stmt FROM @sql;
EXECUTE cj_portal_stmt;
DEALLOCATE PREPARE cj_portal_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cj_orders' AND COLUMN_NAME = 'refund_time'),
		'SELECT 1',
		'ALTER TABLE cj_orders ADD COLUMN refund_time DATETIME NULL AFTER out_stock_time'
	)
);
PREPARE cj_portal_stmt FROM @sql;
EXECUTE cj_portal_stmt;
DEALLOCATE PREPARE cj_portal_stmt;

SET @sql := (
	SELECT IF(
		EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cj_orders' AND COLUMN_NAME = 'fail_remark'),
		'SELECT 1',
		'ALTER TABLE cj_orders ADD COLUMN fail_remark TEXT NULL AFTER time_zone_id'
	)
);
PREPARE cj_portal_stmt FROM @sql;
EXECUTE cj_portal_stmt;
DEALLOCATE PREPARE cj_portal_stmt;

UPDATE cj_orders
SET pay_way = CAST(
	COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.payWay')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.pay_way')), 'null')
	) AS SIGNED
)
WHERE pay_way IS NULL
	AND JSON_VALID(raw_json)
	AND COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.payWay')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.pay_way')), 'null'),
		''
	) REGEXP '^-?[0-9]+$';

UPDATE cj_orders
SET out_stock_status = CAST(
	COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.outStockStatus')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.out_stock_status')), 'null')
	) AS SIGNED
)
WHERE out_stock_status IS NULL
	AND JSON_VALID(raw_json)
	AND COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.outStockStatus')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.out_stock_status')), 'null'),
		''
	) REGEXP '^-?[0-9]+$';

UPDATE cj_orders
SET out_stock_time = STR_TO_DATE(
	COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.outStockTime')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.out_stock_time')), 'null')
	),
	'%Y-%m-%d %H:%i:%s'
)
WHERE out_stock_time IS NULL
	AND JSON_VALID(raw_json)
	AND COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.outStockTime')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.out_stock_time')), 'null'),
		''
	) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$';

UPDATE cj_orders
SET refund_time = STR_TO_DATE(
	COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.refundTime')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.refund_time')), 'null')
	),
	'%Y-%m-%d %H:%i:%s'
)
WHERE refund_time IS NULL
	AND JSON_VALID(raw_json)
	AND COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.refundTime')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.refund_time')), 'null'),
		''
	) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$';

UPDATE cj_orders
SET fail_remark = COALESCE(
	NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.failRemark')), 'null'),
	NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.fail_remark')), 'null')
)
WHERE (fail_remark IS NULL OR fail_remark = '')
	AND JSON_VALID(raw_json)
	AND COALESCE(
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.failRemark')), 'null'),
		NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.fail_remark')), 'null'),
		''
	) <> '';
