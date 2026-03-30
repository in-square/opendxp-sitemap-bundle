CREATE TABLE IF NOT EXISTS `sitemap_item` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `element_type` VARCHAR(16) NOT NULL,
  `element_id` INT NOT NULL,
  `element_class` VARCHAR(255) DEFAULT NULL,
  `site_id` INT NOT NULL,
  `locale` VARCHAR(16) NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `lastmod` DATETIME NOT NULL,
  `priority` DOUBLE PRECISION DEFAULT NULL,
  `changefreq` VARCHAR(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
