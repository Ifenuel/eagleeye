<?php
/**
 * Backup health and recovery checklist.
 *
 * The daily database dump is only useful if it can be read and restored. This
 * page verifies the newest dump instead of assuming that a file existing means
 * a backup works. No restore is ever run from here.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backup directory used by the server's daily dump plugin.
 *
 * @return string
 */
function eagleeye_backup_dir() {
	if ( function_exists( 'ee_backup_dir' ) ) {
		return ee_backup_dir();
	}

	return trailingslashit( dirname( ABSPATH, 3 ) ) . 'private/ee-db-backups';
}

/**
 * Backup files, newest first.
 *
 * @return array<int,array<string,mixed>>
 */
function eagleeye_backup_files() {
	$dir   = eagleeye_backup_dir();
	$files = glob( $dir . '/db-*.sql.gz' );

	if ( ! is_array( $files ) ) {
		return array();
	}

	usort( $files, function ( $a, $b ) {
		return filemtime( $b ) <=> filemtime( $a );
	} );

	$out = array();

	foreach ( $files as $file ) {
		$out[] = array(
			'path'  => $file,
			'name'  => basename( $file ),
			'size'  => (int) filesize( $file ),
			'time'  => (int) filemtime( $file ),
			'age'   => ( time() - (int) filemtime( $file ) ) / HOUR_IN_SECONDS,
		);
	}

	return $out;
}

/**
 * Check whether a dump is a readable gzip archive that contains the core tables.
 *
 * @param string $path Dump path.
 * @return array{ok:bool,message:string,bytes:int}
 */
function eagleeye_backup_verify( $path ) {
	$result = array(
		'ok'      => false,
		'message' => '',
		'bytes'   => 0,
	);

	if ( ! is_readable( $path ) ) {
		$result['message'] = __( 'The file is not readable.', 'eagleeye' );
		return $result;
	}

	$handle = @gzopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( ! $handle ) {
		$result['message'] = __( 'The file is not a readable gzip archive.', 'eagleeye' );
		return $result;
	}

	global $wpdb;

	$bytes      = 0;
	$has_create = false;
	$has_core   = false;
	$tail       = '';
	$pattern    = '/CREATE TABLE `[^`]*posts`/';

	while ( ! gzeof( $handle ) ) {
		$chunk = gzread( $handle, 65536 );

		if ( false === $chunk ) {
			gzclose( $handle );
			$result['message'] = __( 'The archive is corrupt and could not be decompressed.', 'eagleeye' );
			return $result;
		}

		// Include the end of the previous chunk so a statement split across
		// the boundary is still matched.
		$search = $tail . $chunk;

		if ( ! $has_create && false !== strpos( $search, 'CREATE TABLE' ) ) {
			$has_create = true;
		}

		if ( ! $has_core && preg_match( $pattern, $search ) ) {
			$has_core = true;
		}

		$tail   = substr( $chunk, -512 );
		$bytes += strlen( $chunk );
	}

	gzclose( $handle );

	$result['bytes'] = $bytes;

	if ( $bytes < 1000 ) {
		$result['message'] = __( 'The archive decompressed to almost nothing, so it is probably incomplete.', 'eagleeye' );
		return $result;
	}

	if ( ! $has_create ) {
		$result['message'] = __( 'The archive contains no table definitions.', 'eagleeye' );
		return $result;
	}

	if ( ! $has_core ) {
		$result['message'] = sprintf(
			/* translators: %s: database table prefix. */
			__( 'No posts table was found. The dump may be incomplete or use an unexpected prefix (%s).', 'eagleeye' ),
			(string) $wpdb->prefix
		);
		return $result;
	}

	$result['ok']      = true;
	$result['message'] = __( 'Archive opens, decompresses and contains WordPress tables.', 'eagleeye' );

	return $result;
}

/**
 * Directory holding the file archives, outside the web root.
 *
 * @return string
 */
function eagleeye_file_backup_dir() {
	return dirname( dirname( dirname( ABSPATH ) ) ) . '/private/ee-file-backups';
}

/**
 * File archives of a given kind, newest first.
 *
 * @param string $kind files|theme.
 * @return array<int,array<string,mixed>>
 */
function eagleeye_file_backup_files( $kind = 'files' ) {
	$paths = glob( eagleeye_file_backup_dir() . '/' . $kind . '-*.tar.gz' );

	if ( ! is_array( $paths ) ) {
		return array();
	}

	usort( $paths, function ( $a, $b ) {
		return filemtime( $b ) <=> filemtime( $a );
	} );

	$out = array();

	foreach ( $paths as $path ) {
		$out[] = array(
			'name' => basename( $path ),
			'size' => (int) filesize( $path ),
			'time' => (int) filemtime( $path ),
			'age'  => ( time() - (int) filemtime( $path ) ) / HOUR_IN_SECONDS,
		);
	}

	return $out;
}

/**
 * Add the recovery page for administrators.
 */
function eagleeye_backup_menu() {
	add_submenu_page(
		'eagleeye',
		__( 'Backups & recovery', 'eagleeye' ),
		__( 'Backups & recovery', 'eagleeye' ),
		'manage_options',
		'eagleeye-backups',
		'eagleeye_backup_page'
	);
}
add_action( 'admin_menu', 'eagleeye_backup_menu', 13 );

/**
 * Run a backup on demand.
 */
function eagleeye_backup_run_now() {
	check_admin_referer( 'eagleeye_backup_now' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to run a backup.', 'eagleeye' ), 403 );
	}

	$result = function_exists( 'ee_run_backup' ) ? ee_run_backup( true ) : false;

	wp_safe_redirect(
		add_query_arg(
			'ee_backup',
			$result ? 'done' : 'failed',
			admin_url( 'admin.php?page=eagleeye-backups' )
		)
	);
	exit;
}
add_action( 'admin_post_eagleeye_backup_now', 'eagleeye_backup_run_now' );

/**
 * Render the recovery page.
 */
function eagleeye_backup_page() {
	$files   = eagleeye_backup_files();
	$newest  = $files ? $files[0] : null;
	$verify  = $newest ? eagleeye_backup_verify( $newest['path'] ) : null;
	$stale   = $newest && $newest['age'] > 36;
	$missing = ! $newest;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Backups & recovery', 'eagleeye' ); ?></h1>

		<?php if ( isset( $_GET['ee_backup'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php $state = sanitize_key( wp_unslash( $_GET['ee_backup'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice <?php echo 'done' === $state ? 'notice-success' : 'notice-error'; ?> is-dismissible">
				<p>
					<?php
					echo 'done' === $state
						? esc_html__( 'A new database backup was written. Verify it below before relying on it.', 'eagleeye' )
						: esc_html__( 'The backup could not be written. Check the server error log and disk space.', 'eagleeye' );
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $missing ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'No database backup files were found. Do not make changes until a working backup exists.', 'eagleeye' ); ?></p></div>
		<?php elseif ( $stale ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( sprintf( __( 'The newest backup is %s hours old. Check that the daily schedule is running.', 'eagleeye' ), number_format_i18n( round( $newest['age'] ) ) ) ); ?></p></div>
		<?php endif; ?>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Newest database backup', 'eagleeye' ); ?></h2>

			<?php if ( $newest && $verify ) : ?>
				<table class="widefat striped" style="max-width:760px">
					<tbody>
						<tr><th style="width:190px"><?php esc_html_e( 'File', 'eagleeye' ); ?></th><td><?php echo esc_html( $newest['name'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Written', 'eagleeye' ); ?></th><td><?php echo esc_html( date_i18n( 'j F Y, H:i', $newest['time'] ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Compressed size', 'eagleeye' ); ?></th><td><?php echo esc_html( size_format( $newest['size'] ) ); ?></td></tr>
						<tr>
							<th><?php esc_html_e( 'Read check', 'eagleeye' ); ?></th>
							<td>
								<strong style="color:<?php echo $verify['ok'] ? '#116b2f' : '#b32d2e'; ?>">
									<?php echo $verify['ok'] ? esc_html__( 'Passed', 'eagleeye' ) : esc_html__( 'Failed', 'eagleeye' ); ?>
								</strong>
								&mdash; <?php echo esc_html( $verify['message'] ); ?>
								<?php if ( $verify['bytes'] ) : ?>
									<span style="color:#666">(<?php echo esc_html( size_format( $verify['bytes'] ) ); ?> of SQL)</span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			<?php elseif ( ! $newest ) : ?>
				<p><?php esc_html_e( 'Nothing to verify yet.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<p style="margin-top:16px">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="eagleeye_backup_now">
					<?php wp_nonce_field( 'eagleeye_backup_now' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Run a backup now', 'eagleeye' ); ?></button>
				</form>
			</p>
			<p class="description"><?php esc_html_e( 'The read check proves the archive opens and contains WordPress tables. It does not prove a restore will succeed; that needs a test restore on a copy of the site.', 'eagleeye' ); ?></p>
		</div>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Recent backups', 'eagleeye' ); ?></h2>
			<?php if ( $files ) : ?>
				<table class="widefat striped" style="max-width:760px">
					<thead><tr><th><?php esc_html_e( 'File', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Written', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Size', 'eagleeye' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( array_slice( $files, 0, 10 ) as $file ) : ?>
							<tr>
								<td><?php echo esc_html( $file['name'] ); ?></td>
								<td><?php echo esc_html( date_i18n( 'j M Y, H:i', $file['time'] ) ); ?></td>
								<td><?php echo esc_html( size_format( $file['size'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'The server keeps the last seven daily dumps.', 'eagleeye' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No backups found yet.', 'eagleeye' ); ?></p>
			<?php endif; ?>
		</div>

		<?php $file_archives = eagleeye_file_backup_files( 'files' ); $theme_archives = eagleeye_file_backup_files( 'theme' ); ?>
		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Website files', 'eagleeye' ); ?></h2>

			<?php if ( $file_archives ) : ?>
				<table class="widefat striped" style="max-width:760px">
					<thead><tr><th><?php esc_html_e( 'Archive', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Written', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Size', 'eagleeye' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $file_archives as $archive ) : ?>
							<tr><td><?php echo esc_html( $archive['name'] ); ?></td><td><?php echo esc_html( date_i18n( 'j M Y, H:i', $archive['time'] ) ); ?></td><td><?php echo esc_html( size_format( $archive['size'] ) ); ?></td></tr>
						<?php endforeach; ?>
						<?php foreach ( $theme_archives as $archive ) : ?>
							<tr><td><?php echo esc_html( $archive['name'] ); ?></td><td><?php echo esc_html( date_i18n( 'j M Y, H:i', $archive['time'] ) ); ?></td><td><?php echo esc_html( size_format( $archive['size'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $file_archives[0]['age'] > 8 * 24 ) : ?>
					<div class="notice notice-warning inline" style="margin:12px 0"><p><?php echo esc_html( sprintf( __( 'The newest file archive is %s days old.', 'eagleeye' ), number_format_i18n( round( $file_archives[0]['age'] / 24 ) ) ) ); ?></p></div>
				<?php endif; ?>

				<p class="description"><?php esc_html_e( 'Each archive is checked with gzip and tar before it is kept, and a SHA-256 checksum sits beside it. The newest two of each kind are retained.', 'eagleeye' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No file archives found yet. Run the backup script on the server to create one.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<p style="margin-bottom:6px"><strong><?php esc_html_e( 'Schedule it weekly', 'eagleeye' ); ?></strong></p>
			<p><?php esc_html_e( 'Add a cron job in hPanel → Advanced → Cron Jobs that runs this command once a week (for example Sunday at 03:00):', 'eagleeye' ); ?></p>
			<pre style="background:#f6f7f7;padding:10px;overflow:auto;max-width:900px">/usr/bin/env bash /home/u414062733/backup-tools/backup-files.sh &gt;&gt; /home/u414062733/private/ee-file-backups/backup.log 2&gt;&amp;1</pre>
			<p class="description"><?php esc_html_e( 'Until that cron job exists, file archives are only as fresh as the last manual run. The database dump is scheduled separately and runs daily.', 'eagleeye' ); ?></p>

			<p><strong><?php esc_html_e( 'Restoring files', 'eagleeye' ); ?></strong> <?php esc_html_e( 'Extract an archive into wp-content on a test copy first:', 'eagleeye' ); ?></p>
			<pre style="background:#f6f7f7;padding:10px;overflow:auto;max-width:900px">tar xzf files-YYYYMMDD-HHMMSS.tar.gz -C /path/to/public_html/wp-content</pre>
		</div>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'What these backups do not cover', 'eagleeye' ); ?></h2>
			<ul style="margin-left:18px;list-style:disc">
				<li><?php esc_html_e( 'Plugins and WordPress core are not in the file archive; they can be reinstalled from the version you already use.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'The file archive only covers uploads, private report media and the theme.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'Nothing here is stored off the hosting account, so a lost account loses these backups too.', 'eagleeye' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Download the newest archives occasionally, or ask the host about account-level backups kept separately.', 'eagleeye' ); ?></p>
		</div>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Restore procedure', 'eagleeye' ); ?></h2>

			<?php $restore_test = get_option( 'eagleeye_last_restore_test' ); ?>
			<?php if ( is_array( $restore_test ) && ! empty( $restore_test['ok'] ) ) : ?>
				<p style="padding:10px 12px;background:#edf7ee;border-left:4px solid #116b2f">
					<strong><?php esc_html_e( 'Last verified restore test passed.', 'eagleeye' ); ?></strong>
					<?php
					printf(
						esc_html__( '%1$s · %2$d tables matched the live row counts · archive %3$s', 'eagleeye' ),
						esc_html( (string) ( $restore_test['time'] ?? '' ) ),
						(int) ( $restore_test['tables'] ?? 0 ),
						esc_html( (string) ( $restore_test['dump'] ?? '' ) )
					);
					?>
				</p>
			<?php else : ?>
				<p style="padding:10px 12px;background:#fcf3e3;border-left:4px solid #c07a00"><?php esc_html_e( 'No restore test has been recorded yet. A readable archive is not the same as a restorable one.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<p><?php esc_html_e( 'The check imports the newest dump into prefixed scratch tables inside the same database, compares row counts with the live tables, then drops the scratch tables. Live tables are never written to.', 'eagleeye' ); ?></p>
			<p><?php esc_html_e( 'Restore only on a copy of the site first. Never test a restore over the live site.', 'eagleeye' ); ?></p>
			<ol style="margin-left:18px">
				<li><?php esc_html_e( 'Create or point to a staging copy with its own database.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'Download the chosen dump and the matching uploads archive.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'Import the dump into the staging database, then check the homepage, a story, the media library, and the Citizen Reports inbox.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'Only after the staging copy works, repeat the same steps on live during a quiet period.', 'eagleeye' ); ?></li>
				<li><?php esc_html_e( 'Keep the pre-restore dump until the site has run normally for at least a day.', 'eagleeye' ); ?></li>
			</ol>
			<p class="description"><?php esc_html_e( 'Commands vary by host. On Hostinger, use the hPanel backup/restore tools or the database import in phpMyAdmin rather than guessing at command-line paths.', 'eagleeye' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Warn administrators on the newsroom page when the newest dump is stale.
 */
function eagleeye_backup_stale_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'toplevel_page_eagleeye' !== $screen->id ) {
		return;
	}

	$files = eagleeye_backup_files();

	if ( ! $files ) {
		printf(
			'<div class="notice notice-error"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'No database backup was found. Check the backup schedule before publishing changes.', 'eagleeye' ),
			esc_url( admin_url( 'admin.php?page=eagleeye-backups' ) ),
			esc_html__( 'Open backups', 'eagleeye' )
		);
		return;
	}

	if ( $files[0]['age'] > 36 ) {
		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( sprintf( __( 'The newest database backup is %s hours old.', 'eagleeye' ), number_format_i18n( round( $files[0]['age'] ) ) ) ),
			esc_url( admin_url( 'admin.php?page=eagleeye-backups' ) ),
			esc_html__( 'Check backups', 'eagleeye' )
		);
	}
}
add_action( 'admin_notices', 'eagleeye_backup_stale_notice' );
