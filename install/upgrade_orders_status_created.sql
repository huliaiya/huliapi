DELIMITER $$
DROP PROCEDURE IF EXISTS upgrade_orders_status_created $$
CREATE PROCEDURE upgrade_orders_status_created()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'huli_orders' AND INDEX_NAME = 'status_created') THEN
        ALTER TABLE huli_orders ADD INDEX `status_created` (`status`,`created_at`);
    END IF;
END $$
DELIMITER ;
CALL upgrade_orders_status_created();
DROP PROCEDURE IF EXISTS upgrade_orders_status_created;
