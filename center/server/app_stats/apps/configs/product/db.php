<?php
$db['master'] = array(
    'type'    => Swoole\Database::TYPE_MYSQLi,
    'host'    => "192.168.1.235",
    'port'    => 3306,
    'dbms'    => 'mysql',
    'engine'  => 'MyISAM',
    'user'    => "root",
    'passwd'  => "app_stats@0519",
    'name'    => "app_stats",
    'charset' => "utf8",
    'setname' => true,
);
return $db;