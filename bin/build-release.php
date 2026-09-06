<?php
/**
 * Build a WordPress.org production zip: dist/bits-markdown-{version}.zip
 *
 * Copies the plugin (honoring .distignore), runs `composer install --no-dev`,
 * and zips a single root folder named bits-markdown.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$plugin_file = $root . '/bits-markdown.php';

if ( ! is_readable( $plugin_file ) ) {
	fwrite( STDERR, "Missing bits-markdown.php\n" );
	exit( 1 );
}

$header = file_get_contents( $plugin_file );
if ( $header === false || ! preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', $header, $match ) ) {
	fwrite( STDERR, "Could not read Version from bits-markdown.php\n" );
	exit( 1 );
}

$version = trim( $match[1] );
$slug    = 'bits-markdown';

$patterns = array( '.git', 'vendor' );
$distignore = $root . '/.distignore';
if ( is_readable( $distignore ) ) {
	foreach ( file( $distignore, FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = trim( $line );
		if ( $line === '' || str_starts_with( $line, '#' ) ) {
			continue;
		}
		$patterns[] = $line;
	}
	$patterns = array_values( array_unique( $patterns ) );
}

$stage = sys_get_temp_dir() . '/bits-markdown-release-' . bin2hex( random_bytes( 4 ) );
$dest  = $stage . '/' . $slug;
if ( ! mkdir( $dest, 0755, true ) && ! is_dir( $dest ) ) {
	fwrite( STDERR, "Could not create staging directory\n" );
	exit( 1 );
}

$cleanup = static function () use ( $stage ): void {
	if ( ! is_dir( $stage ) ) {
		return;
	}
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $stage, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $files as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $stage );
};

register_shutdown_function( $cleanup );

$dir_iterator = new RecursiveDirectoryIterator(
	$root,
	FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
);
$filter = new RecursiveCallbackFilterIterator(
	$dir_iterator,
	static function ( $current ) use ( $root, $patterns ): bool {
		$relative = substr( $current->getPathname(), strlen( $root ) + 1 );
		$relative = str_replace( '\\', '/', $relative );
		return ! should_exclude( $relative, $patterns );
	}
);
$iterator = new RecursiveIteratorIterator( $filter );

foreach ( $iterator as $file ) {
	$absolute = $file->getPathname();
	$relative = substr( $absolute, strlen( $root ) + 1 );
	$relative = str_replace( '\\', '/', $relative );

	$target = $dest . '/' . $relative;
	if ( $file->isDir() ) {
		if ( ! is_dir( $target ) && ! mkdir( $target, 0755, true ) ) {
			fwrite( STDERR, "Could not create $target\n" );
			exit( 1 );
		}
		continue;
	}

	$dir = dirname( $target );
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) {
		fwrite( STDERR, "Could not create $dir\n" );
		exit( 1 );
	}

	if ( ! copy( $absolute, $target ) ) {
		fwrite( STDERR, "Could not copy $relative\n" );
		exit( 1 );
	}
}

if ( ! is_readable( $dest . '/bits-markdown.php' ) || ! is_readable( $dest . '/composer.lock' ) ) {
	fwrite( STDERR, "Staging copy is missing bits-markdown.php or composer.lock\n" );
	exit( 1 );
}

$composer = 'composer';
$cmd      = sprintf(
	'cd %s && %s install --no-dev --optimize-autoloader --no-interaction --no-progress',
	escapeshellarg( $dest ),
	escapeshellarg( $composer )
);

passthru( $cmd, $composer_status );
if ( $composer_status !== 0 ) {
	fwrite( STDERR, "composer install --no-dev failed\n" );
	exit( $composer_status );
}

$vendor_bin = $dest . '/vendor/bin';
if ( is_dir( $vendor_bin ) ) {
	foreach ( glob( $vendor_bin . '/*' ) ?: array() as $bin ) {
		unlink( $bin );
	}
	rmdir( $vendor_bin );
}

$dist = $root . '/dist';
if ( ! is_dir( $dist ) && ! mkdir( $dist, 0755, true ) ) {
	fwrite( STDERR, "Could not create dist/\n" );
	exit( 1 );
}

$zip_name = $slug . '-' . $version . '.zip';
$zip_path = $dist . '/' . $zip_name;
if ( is_file( $zip_path ) ) {
	unlink( $zip_path );
}

$zip_cmd = sprintf(
	'cd %s && zip -r -q %s %s',
	escapeshellarg( $stage ),
	escapeshellarg( $zip_path ),
	escapeshellarg( $slug )
);
passthru( $zip_cmd, $zip_status );
if ( $zip_status !== 0 ) {
	fwrite( STDERR, "zip failed\n" );
	exit( $zip_status );
}

$bytes = filesize( $zip_path );
$hash  = hash_file( 'sha256', $zip_path );
echo $zip_path . "\n";
echo 'size: ' . $bytes . " bytes\n";
echo 'sha256: ' . $hash . "\n";

/**
 * @param list<string> $patterns
 */
function should_exclude( string $relative, array $patterns ): bool {
	foreach ( $patterns as $pattern ) {
		$pattern = trim( $pattern, '/' );
		if ( $pattern === '' ) {
			continue;
		}
		if ( str_contains( $pattern, '*' ) || str_contains( $pattern, '?' ) ) {
			if ( fnmatch( $pattern, $relative ) || fnmatch( $pattern, basename( $relative ) ) ) {
				return true;
			}
			continue;
		}
		if ( $relative === $pattern || str_starts_with( $relative, $pattern . '/' ) ) {
			return true;
		}
	}

	return false;
}
