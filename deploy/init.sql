CREATE DATABASE IF NOT EXISTS laravel_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON hotels.* TO 'hotel'@'%';
GRANT ALL ON laravel_hotel.* TO 'hotel'@'%';
