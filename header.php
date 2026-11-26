<?php
/**
 * Site header.
 *
 * @package Eagle Eye
 */

$socials = eagleeye_socials();
$ad_page = get_page_by_path( 'advertise-with-us' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'eagleeye' ); ?></a>

<div class="ee-top">
	<div class="wrap ee-top__inner">
		<p class="ee-top__date"><?php echo esc_html( date_i18n( 'l, j F Y' ) ); ?></p>
		<p class="ee-top__note"><?php echo esc_html( eagleeye_option( 'eagleeye_masthead_note', __( 'Independent journalism for a changing nation', 'eagleeye' ) ) ); ?></p>
		<div class="ee-top__links">
			<?php if ( $socials ) : ?>
				<ul class="socials socials--top" aria-label="<?php esc_attr_e( 'Eagle Eye on social media', 'eagleeye' ); ?>">
					<?php foreach ( $socials as $key => $social ) : ?>
						<li><a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $social['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<a class="ee-top__cta" href="<?php echo esc_url( home_url( '/#newsletter' ) ); ?>"><?php esc_html_e( 'Newsletters', 'eagleeye' ); ?></a>
		</div>
	</div>
</div>

<header class="ee-head">
	<div class="wrap ee-head__inner">
		<button class="ee-burger" type="button" aria-expanded="false" aria-controls="mobile-drawer" aria-label="<?php esc_attr_e( 'Open sections menu', 'eagleeye' ); ?>" data-nav-toggle>
			<span class="ee-burger__bars" aria-hidden="true"><span></span><span></span><span></span></span>
			<span class="ee-burger__text"><?php esc_html_e( 'Sections', 'eagleeye' ); ?></span>
		</button>

		<a class="ee-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — <?php esc_attr_e( 'Home', 'eagleeye' ); ?>">
			<?php eagleeye_brand_identity( 'dark' ); ?>
		</a>

		<div class="ee-head__tools">
			<button class="ee-tool ee-tool--icon" type="button" data-search-open aria-label="<?php esc_attr_e( 'Search Eagle Eye', 'eagleeye' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16.5 16.5 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
				<span><?php esc_html_e( 'Search', 'eagleeye' ); ?></span>
			</button>
			<a class="ee-tool ee-tool--solid" href="<?php echo esc_url( home_url( '/#newsletter' ) ); ?>"><?php esc_html_e( 'Subscribe', 'eagleeye' ); ?></a>
		</div>
	</div>
</header>

<nav class="ee-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'eagleeye' ); ?>" data-sticky-nav>
	<div class="wrap ee-nav__inner">
		<?php
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => 'ee-nav__menu',
			'depth'          => 2,
			'fallback_cb'    => 'eagleeye_nav_fallback',
		) );
		?>
		<div class="ee-nav__tools">
			<?php if ( $ad_page ) : ?>
				<a class="ee-nav__advertise" href="<?php echo esc_url( get_permalink( $ad_page ) ); ?>"><?php esc_html_e( 'Advertise', 'eagleeye' ); ?></a>
			<?php endif; ?>
			<a class="ee-nav__search" href="#search" data-search-open>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16.5 16.5 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'eagleeye' ); ?></span>
			</a>
		</div>
	</div>
</nav>

<div class="drawer" id="mobile-drawer" hidden>
	<div class="drawer__head">
		<a class="ee-brand ee-brand--drawer" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php eagleeye_brand_identity( 'dark', 'ee-brand--drawer' ); ?>
		</a>
		<button class="drawer__close" type="button" data-nav-close aria-label="<?php esc_attr_e( 'Close navigation', 'eagleeye' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5 5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
		</button>
	</div>
	<?php
	wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'menu_class'     => 'drawer__menu',
		'depth'          => 2,
		'fallback_cb'    => 'eagleeye_nav_fallback',
	) );
	?>
	<div class="drawer__foot">
		<a class="btn btn--gold" href="<?php echo esc_url( home_url( '/#newsletter' ) ); ?>"><?php esc_html_e( 'Get the morning brief', 'eagleeye' ); ?></a>
		<?php if ( $ad_page ) : ?>
			<a class="drawer__link" href="<?php echo esc_url( get_permalink( $ad_page ) ); ?>"><?php esc_html_e( 'Advertise with Eagle Eye', 'eagleeye' ); ?></a>
		<?php endif; ?>
		<?php if ( $socials ) : ?>
			<ul class="socials socials--drawer">
				<?php foreach ( $socials as $key => $social ) : ?>
					<li><a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $social['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
<div class="drawer-scrim" data-nav-close hidden></div>

<div class="search-panel" id="search" hidden>
	<div class="search-panel__inner wrap">
		<form role="search" method="get" class="search-panel__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="search-panel-field"><?php esc_html_e( 'Search Eagle Eye', 'eagleeye' ); ?></label>
			<input type="search" id="search-panel-field" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search stories, sections and authors…', 'eagleeye' ); ?>">
			<button type="submit" class="btn btn--solid"><?php esc_html_e( 'Search', 'eagleeye' ); ?></button>
		</form>
		<button class="search-panel__close" type="button" data-search-close><?php esc_html_e( 'Close', 'eagleeye' ); ?></button>
	</div>
</div>

<?php get_template_part( 'template-parts/ticker' ); ?>

<main id="main" class="site-main">
