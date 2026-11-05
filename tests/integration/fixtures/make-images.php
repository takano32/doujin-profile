<?php
/**
 * 結合試験の見本の画像 (works.json が指す書影とサンプル) を GD で作る。リポジトリに画像を置かないため。
 *
 *   php tests/integration/fixtures/make-images.php <出力先のディレクトリ>
 */

$dir = $argv[1] ?? '';
if ( '' === $dir || ! is_dir( $dir ) ) {
	fwrite( STDERR, "使い方: php make-images.php <出力先のディレクトリ>\n" );
	exit( 1 );
}

$images = array(
	'cover1.jpg'  => array( 300, 420, array( 220, 80, 120 ) ),
	'cover2.jpg'  => array( 300, 420, array( 80, 120, 220 ) ),
	'sample1.jpg' => array( 320, 450, array( 240, 240, 240 ) ),
	'sample2.jpg' => array( 320, 450, array( 200, 200, 200 ) ),
	'sample3.jpg' => array( 320, 450, array( 160, 160, 160 ) ),
);
foreach ( $images as $name => list( $width, $height, $rgb ) ) {
	$image = imagecreatetruecolor( $width, $height );
	imagefilledrectangle( $image, 0, 0, $width, $height, imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] ) );
	imagestring( $image, 5, 10, 10, $name, imagecolorallocate( $image, 0, 0, 0 ) );
	imagejpeg( $image, "{$dir}/{$name}", 85 );
	imagedestroy( $image );
}
echo count( $images ) . " 枚の画像を {$dir} に作った\n";
