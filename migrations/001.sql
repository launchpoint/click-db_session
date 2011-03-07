CREATE TABLE IF NOT EXISTS `sessions` (
 `id` varchar(32) NOT NULL,
 `http_user_agent` varchar(32) NOT NULL,
 `data` blob NOT NULL,
 `expire` int(11) NOT NULL default '0',
 PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;