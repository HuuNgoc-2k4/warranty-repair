CREATE DATABASE IF NOT EXISTS wr_gateway   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS wr_warranty  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS wr_repair    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS wr_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'wr'@'%' IDENTIFIED BY 'wr_dev';
GRANT ALL ON wr_gateway.*   TO 'wr'@'%';
GRANT ALL ON wr_warranty.*  TO 'wr'@'%';
GRANT ALL ON wr_repair.*    TO 'wr'@'%';
GRANT ALL ON wr_inventory.* TO 'wr'@'%';
