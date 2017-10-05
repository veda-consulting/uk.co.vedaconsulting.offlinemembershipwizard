-- create table to hold payment processor warning messages
CREATE TABLE IF NOT EXISTS `civicrm_payment_processor_warning_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_processor_id` int(10) NOT NULL,
  `warning_message` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UI_payment_processor_id` (`payment_processor_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;