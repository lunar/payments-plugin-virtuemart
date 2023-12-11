CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_lunar_mobilepay` (
			`id` INT(1) UNSIGNED NOT NULL AUTO_INCREMENT,
			`virtuemart_order_id` INT(1) UNSIGNED NOT NULL,
			`transaction_id` VARCHAR(1000),
			`payment_order_total` DECIMAL(15,5) NOT NULL DEFAULT '0.00000',
			`payment_currency` CHAR(3),
			`email_currency` CHAR(3),
			`order_number` CHAR(64),
			`virtuemart_paymentmethod_id` MEDIUMINT(1) UNSIGNED,
			`payment_name` VARCHAR(5000),
			`cost_per_transaction` DECIMAL(10,2),
			`cost_min_transaction` DECIMAL(10,2),
			`cost_percent_total` DECIMAL(10,2),
			`tax_id` SMALLINT(1),
	PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;