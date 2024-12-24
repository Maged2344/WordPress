<?php
define( "DB_NAME", "wordpress_db" );
define( "DB_USER", "wp_user" );
define( "DB_PASSWORD", "maged500" );
define( "DB_HOST", "10.0.2.174" );
define( "DB_CHARSET", "utf8mb4" );
define( "DB_COLLATE", "" );
$table_prefix  = "wp_";
if ( !defined("ABSPATH") )
    define("ABSPATH", dirname(__FILE__) . "/");
require_once(ABSPATH . "wp-settings.php");
