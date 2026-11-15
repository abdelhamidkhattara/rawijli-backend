CREATE DATABASE IF NOT EXISTS rawijli_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON rawijli_test.* TO 'rawijli'@'%';
FLUSH PRIVILEGES;
