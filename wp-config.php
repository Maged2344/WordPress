<?php
define( "DB_NAME", "wordpress_db" );
define( "DB_USER", "????" );
define( "DB_PASSWORD", "?????" );
define( "DB_HOST", "?????" );
define( "DB_CHARSET", "utf8mb4" );
define( "DB_COLLATE", "" );
$table_prefix  = "wp_";
if ( !defined("ABSPATH") )
    define("ABSPATH", dirname(__FILE__) . "/");
require_once(ABSPATH . "wp-settings.php");
