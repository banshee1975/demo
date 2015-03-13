test_currency  CREATE TABLE `test_currency` (
                 `char_code` varchar(255) NOT NULL,
                 `is_active` int(3) DEFAULT '0',
                  UNIQUE KEY `char_code` (`char_code`)) ENGINE=MyISAM DEFAULT CHARSET=cp1251