-- migration_002_production_indexes.sql
-- Purpose: Performance indexes for production workload
-- Run ONCE against u777110831_briyani_shop
-- Uses CREATE INDEX IF NOT EXISTS (MariaDB 10.1.4+, supported on 11.x)
-- ----------------------------------------------------------------

-- daily_entries: most queries filter by branch_id + entry_date
CREATE INDEX IF NOT EXISTS `idx_de_branch_date`
    ON `daily_entries` (`branch_id`, `entry_date`);

-- daily_entries: JOIN with item_rates uses item_name + branch_id
CREATE INDEX IF NOT EXISTS `idx_de_branch_item`
    ON `daily_entries` (`branch_id`, `item_name`);

-- daily_payments: filtered by branch_id + entry_date range
CREATE INDEX IF NOT EXISTS `idx_dp_branch_date`
    ON `daily_payments` (`branch_id`, `entry_date`);

-- online_sales: filtered by branch_id + sale_date + platform
CREATE INDEX IF NOT EXISTS `idx_os_branch_date_platform`
    ON `online_sales` (`branch_id`, `sale_date`, `platform`);

-- item_rates: look up by branch_id + item_name (JOIN key)
CREATE INDEX IF NOT EXISTS `idx_ir_branch_item`
    ON `item_rates` (`branch_id`, `item_name`);

-- item_rates: ordering by sort_order within a branch
CREATE INDEX IF NOT EXISTS `idx_ir_branch_sort`
    ON `item_rates` (`branch_id`, `sort_order`);

-- login_attempts: created in migration_001; listed here for completeness
-- INDEX `idx_ip_time` on `login_attempts` (`ip_address`, `attempted_at`) — already in migration_001

-- Verify indexes were created:
-- SHOW INDEX FROM daily_entries;
-- SHOW INDEX FROM daily_payments;
-- SHOW INDEX FROM online_sales;
-- SHOW INDEX FROM item_rates;
