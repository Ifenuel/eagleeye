<?php
/**
 * Template Name: Community Eye
 *
 * @package Eagle Eye
 */

get_header();
?>
<header class="page-hero page-hero--dark">
	<div class="wrap page-hero__inner">
		<div>
			<?php eagleeye_breadcrumbs(); ?>
			<span class="eyebrow eyebrow--light"><?php esc_html_e( 'Eagle Eye Citizen Reporter', 'eagleeye' ); ?></span>
			<h1 class="page-hero__title"><?php esc_html_e( 'Community Eye', 'eagleeye' ); ?></h1>
			<p class="page-hero__dek"><?php esc_html_e( 'Your Community. Your Voice. Your Development.', 'eagleeye' ); ?></p>
		</div>
	</div>
</header>

<article class="wrap campus-eye-page__intro">
	<p>Welcome to Community Eye, a people-powered platform by Eagle Eye News dedicated to amplifying community voices, highlighting local realities and promoting grassroots development.</p>
	<p>Every community has a story. Every neighbourhood has its challenges, and every resident has a voice that deserves to be heard.</p>
	<p>Through Community Eye, residents can become active contributors to public awareness and community development by sharing pictures, recording videos, submitting reports and documenting issues affecting their neighbourhoods.</p>
	<p>Is your road in bad condition? Is your community lacking access to clean water, electricity, healthcare or good schools? Is there an abandoned public project that needs government attention? Or is something positive happening in your community that deserves recognition?</p>
	<p>We want to hear about it.</p>
	<p>Community Eye provides an opportunity for residents to bring these issues to public attention, engage relevant authorities and encourage government at all levels to respond to the needs of the people.</p>

	<h2><?php esc_html_e( 'What you can share', 'eagleeye' ); ?></h2>
	<ul>
		<li><strong>Community Challenges:</strong> Report bad roads, poor drainage, flooding, insecurity and other local concerns.</li>
		<li><strong>Picture Reports:</strong> Take photographs of community challenges, abandoned projects and developmental needs.</li>
		<li><strong>Video Reports:</strong> Record short videos showing issues that require public and government attention.</li>
		<li><strong>Community Development:</strong> Highlight projects, initiatives and positive developments within your neighbourhood.</li>
		<li><strong>Government Accountability:</strong> Draw attention to unfulfilled promises, neglected infrastructure and public service delivery.</li>
		<li><strong>Community Voices:</strong> Share residents’ opinions, experiences, suggestions and expectations.</li>
		<li><strong>Local Opportunities:</strong> Promote community events, initiatives and activities that bring people together.</li>
	</ul>

	<h2><?php esc_html_e( 'Your community deserves attention!', 'eagleeye' ); ?></h2>
	<p>You do not need to be a professional journalist to tell your community’s story. You only need to be observant, responsible and willing to speak up.</p>
	<p>Take a picture. Record a video. Write a report. Tell us what is happening around you and what you believe should be done to make your community better.</p>
	<p>Eagle Eye News will provide a platform to bring these stories to the attention of the public, relevant government agencies and decision-makers.</p>
	<p>Let’s make community voices impossible to ignore.</p>
	<p><strong>Community Eye — See It. Capture It. Report It. Make a Difference.</strong></p>

	<div class="campus-eye-page__cta">
		<div>
			<p><strong><?php esc_html_e( 'Report your community story', 'eagleeye' ); ?></strong></p>
			<p><?php esc_html_e( 'Describe the issue or development, tell us what the authorities should do, and upload photos or a short video below. Every submission is verified by our editorial team before publication.', 'eagleeye' ); ?></p>
		</div>
		<a class="btn btn--solid" href="#report-form"><?php esc_html_e( 'Report Your Community Story', 'eagleeye' ); ?></a>
	</div>
	<p><a class="audience-desk__link" href="<?php echo esc_url( eagleeye_category_url( 'community-eye' ) ); ?>"><?php esc_html_e( 'Read Community Eye stories', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a></p>

	<?php eagleeye_citizen_report_form( 'community' ); ?>
</article>
<?php
get_footer();
