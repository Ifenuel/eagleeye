<?php
/**
 * Template Name: Campus Eye
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
			<h1 class="page-hero__title"><?php esc_html_e( 'Campus Eye', 'eagleeye' ); ?></h1>
			<p class="page-hero__dek"><?php esc_html_e( 'Your Campus. Your Voice. Your Story.', 'eagleeye' ); ?></p>
		</div>
	</div>
</header>

<article class="wrap campus-eye-page__intro">
	<p>Welcome to Campus Eye, your front-row seat to everything happening across Nigeria’s campuses!</p>
	<p>From breaking campus news and student politics to inspiring stories, academic achievements, entertainment, sports, innovations and trending issues, Campus Eye brings the heartbeat of campus communities directly to you.</p>
	<p>We believe every student has a story worth telling, every student leader has a voice worth hearing, and every campus has developments that deserve the spotlight.</p>
	<p>Whether you are a student, student union executive, campus journalist, aspiring leader or an institution making a difference, Campus Eye provides the platform to share your experiences, showcase achievements, raise important issues and shape conversations that matter.</p>
	<p>We go beyond reporting events; we amplify student voices, celebrate young talents, promote accountability and spotlight the ideas and initiatives shaping the next generation of leaders.</p>

	<h2><?php esc_html_e( 'What you will find here', 'eagleeye' ); ?></h2>
	<ul>
		<li><strong>Campus News:</strong> Latest developments and happenings across institutions.</li>
		<li><strong>Student Politics:</strong> SUG activities, elections, leadership and governance.</li>
		<li><strong>Campus Voices:</strong> Opinions, interviews, debates and student perspectives.</li>
		<li><strong>People &amp; Personalities:</strong> Celebrating exceptional students and inspiring campus leaders.</li>
		<li><strong>Campus Lifestyle:</strong> Entertainment, fashion, culture and social trends.</li>
		<li><strong>Education &amp; Opportunities:</strong> Scholarships, competitions, internships and career opportunities.</li>
		<li><strong>Campus Sports:</strong> Sporting events, competitions and outstanding athletes.</li>
		<li><strong>Investigative &amp; Special Reports:</strong> Important campus issues that deserve attention.</li>
	</ul>

	<h2><?php esc_html_e( 'Your story deserves to be heard!', 'eagleeye' ); ?></h2>
	<p>Campus Eye is not just about students; it is about giving students the power to tell their stories, influence conversations and document the experiences shaping their generation.</p>
	<p>Have a story, an achievement, an event, an opinion or an issue that deserves attention? We want to hear from you.</p>
	<p>Be the eyes on your campus. Be the voice of your generation.</p>
	<p><strong>Campus Eye – Your Campus. Your Voice. Your Story.</strong></p>

	<div class="campus-eye-page__cta">
		<p><?php esc_html_e( 'Share a story, achievement, event, opinion or issue with the Eagle Eye newsroom. Add photos or a short video below.', 'eagleeye' ); ?></p>
		<a class="btn btn--solid" href="#report-form"><?php esc_html_e( 'Submit Your Story', 'eagleeye' ); ?></a>
	</div>
	<p><a class="audience-desk__link" href="<?php echo esc_url( eagleeye_category_url( 'campus-eye' ) ); ?>"><?php esc_html_e( 'Read Campus Eye stories', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a></p>

	<?php eagleeye_citizen_report_form( 'campus' ); ?>
</article>
<?php
get_footer();
