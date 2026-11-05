<?php
/**
 * 単体試験の土台。lib/ のオートローダだけを読む。WordPress は読まない (読まなくて済むのが lib/ の決まり)。
 * WordPress の関数が要る所は、lib/ 側が callable で受けるので、試験は小さな代わりの関数を渡す (tests/php/Unit/Stubs.php)。
 */

$doujin_profile_root = dirname( __DIR__, 2 ) . '/mu-plugins/doujin-profile';

require $doujin_profile_root . '/lib/Autoloader.php';
DoujinProfile\Autoloader::register( $doujin_profile_root . '/lib' );

require __DIR__ . '/Unit/Stubs.php';
