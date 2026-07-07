<?php
/**
 * Plugin Name: Vatan Static Pages Seeder
 * Description: Fills the About and Contact pages with rich Persian content
 *              (hero, mission, value cards, stats, contact methods, hours).
 *              Visit /wp-admin/?vatan_seed_static=1 as administrator to run.
 *              Re-running re-writes the content — delete the file when done
 *              or use it as a fresh-start tool.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_seed_static'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$log = array();
	vatan_sseed_fill_about( $log );
	vatan_sseed_fill_contact( $log );
	vatan_sseed_fill_support( $log );

	wp_die(
		'<h1>Vatan static pages seed report</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( implode( "\n", $log ) )
		. '</pre><p>'
		. '<a href="' . esc_url( home_url( '/about/' ) ) . '" target="_blank">→ View About</a> &middot; '
		. '<a href="' . esc_url( home_url( '/contact/' ) ) . '" target="_blank">→ View Contact</a>'
		. '</p>',
		'Vatan static pages seeder',
		array( 'response' => 200 )
	);
} );

function vatan_sseed_page_id( string $slug ): int {
	$map = (array) get_option( 'vatan_static_pages', array() );
	if ( ! empty( $map[ $slug ] ) ) {
		return (int) $map[ $slug ];
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page ? (int) $page->ID : 0;
}

/**
 * Look up an attachment URL from the media seeder by slug pattern (matches
 * "event:event-singer-stage", "hero:hero-festival-night", etc.). Used to
 * give the About / Contact heroes a real photo backdrop.
 */
function vatan_sseed_image_url( string $slug ): string {
	$state = (array) get_option( 'vatan_media_seeded', array() );
	foreach ( $state['attachments'] ?? array() as $key => $id ) {
		if ( str_ends_with( $key, ':' . $slug ) ) {
			$url = wp_get_attachment_image_url( (int) $id, 'large' );
			if ( $url ) {
				return $url;
			}
		}
	}
	$att = get_page_by_path( $slug, OBJECT, 'attachment' );
	if ( $att ) {
		$url = wp_get_attachment_image_url( (int) $att->ID, 'large' );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

function vatan_sseed_update( int $page_id, string $title, string $content, array &$log, string $label ): void {
	if ( ! $page_id ) {
		$log[] = '[fail ] ' . $label . ' page not found in vatan_static_pages.';
		return;
	}

	// Wrap the HTML in Gutenberg's "html" block markers so wpautop doesn't
	// rewrite the markup. Without this, WP injects </p><p> around the block
	// elements inside our .info-card anchors, which breaks the cards
	// (the link span ends up outside the card and gets its own grid cell).
	$wrapped = "<!-- wp:html -->\n" . trim( $content ) . "\n<!-- /wp:html -->";

	$result = wp_update_post( array(
		'ID'           => $page_id,
		'post_title'   => $title,
		'post_content' => $wrapped,
		'post_status'  => 'publish',
	), true );
	if ( is_wp_error( $result ) ) {
		$log[] = '[fail ] ' . $label . ': ' . $result->get_error_message();
		return;
	}
	$log[] = '[ok   ] ' . $label . ' page #' . $page_id . ' updated (' . strlen( $wrapped ) . ' bytes).';
}

/* =============================================================================
 *  ABOUT page
 * ===========================================================================*/

function vatan_sseed_fill_about( array &$log ): void {
	$id = vatan_sseed_page_id( 'about' );

	$events_url   = home_url( '/events/' );
	$create_url   = home_url( '/create-event/' );
	$hero_image   = vatan_sseed_image_url( 'hero-concert-crowd' );

	ob_start();
	?>
<section class="info-hero">
	<?php if ( $hero_image ) : ?>
		<img class="info-hero__bg" src="<?php echo esc_url( $hero_image ); ?>" alt="" loading="eager" />
	<?php endif; ?>
	<div class="info-hero__overlay" aria-hidden="true"></div>
	<div class="container info-hero__inner">
		<p class="info-hero__eyebrow">درباره ما</p>
		<h1 class="info-hero__title">جایی که رویدادهای فارسی‌زبان <span class="info-hero__title-accent">جان می‌گیرند</span></h1>
		<p class="info-hero__lead">وطن ایونت یک پلتفرم خرید و فروش بلیت برای جامعه فارسی‌زبان در اروپا و سراسر جهان است. ما ارتباط بین هنرمندان، برگزارکنندگان و طرفداران را ساده‌تر می‌کنیم.</p>
	</div>
</section>

<section class="info-section info-mission">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">مأموریت ما</h2>
			<p class="info-section__lead">باور داریم که هیچ فاصله جغرافیایی نباید بین یک طرفدار و هنرمند محبوبش قرار بگیرد. هر فارسی‌زبان، در هر شهر دنیا، باید بتواند با چند کلیک به یک رویداد فرهنگی متصل شود.</p>
		</header>
	</div>
</section>
<?php
	$content = ob_get_clean();
	vatan_sseed_about_continue( $content );

	vatan_sseed_update( $id, 'درباره وطن ایونت', $content, $log, 'about' );
}

function vatan_sseed_about_continue( string &$content ): void {
	$events_url = home_url( '/events/' );
	$create_url = home_url( '/create-event/' );

	ob_start();
	?>
<section class="info-section info-pillars">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">سه ستون پلتفرم ما</h2>
		</header>
		<div class="info-pillars__grid" data-vatan-anim-children>
			<article class="info-card">
				<div class="info-card__icon">🎫</div>
				<h3 class="info-card__title">خرید آسان بلیت</h3>
				<p class="info-card__body">انتخاب صندلی روی نقشه، پرداخت امن، بلیت QR در حساب کاربری — همه فقط با چند کلیک. گارانتی بازگشت تا ۷۲ ساعت پیش از رویداد.</p>
				<a class="info-card__link" href="<?php echo esc_url( $events_url ); ?>">مشاهده رویدادها ←</a>
			</article>
			<article class="info-card">
				<div class="info-card__icon">🎤</div>
				<h3 class="info-card__title">ابزار حرفه‌ای برگزارکننده</h3>
				<p class="info-card__body">رویداد بساز، نقشه صندلی طراحی کن، قیمت‌گذاری چندسطحی تعریف کن. داشبورد فروش لحظه‌ای، گزارش CSV، و پرداخت پس از رویداد.</p>
				<a class="info-card__link" href="<?php echo esc_url( $create_url ); ?>">برگزارکننده شو ←</a>
			</article>
			<article class="info-card">
				<div class="info-card__icon">🌍</div>
				<h3 class="info-card__title">جامعه فارسی‌زبان</h3>
				<p class="info-card__body">از لندن تا برلین، از استکهلم تا تورنتو. هر شهری که هنرمند فارسی‌زبان روی صحنه برود — وطن ایونت هم آنجاست.</p>
				<a class="info-card__link" href="<?php echo esc_url( $events_url ); ?>">شهرها را ببین ←</a>
			</article>
		</div>
	</div>
</section>

<section class="info-section info-stats">
	<div class="container info-section__inner">
		<div class="info-stats__grid" data-vatan-anim-children>
			<div class="info-stat">
				<div class="info-stat__value">۱۰+</div>
				<div class="info-stat__label">رویداد در اروپا</div>
			</div>
			<div class="info-stat">
				<div class="info-stat__value">۶</div>
				<div class="info-stat__label">کشور حضور</div>
			</div>
			<div class="info-stat">
				<div class="info-stat__value">۱۰</div>
				<div class="info-stat__label">شهر پوشش</div>
			</div>
			<div class="info-stat">
				<div class="info-stat__value">۲۴/۷</div>
				<div class="info-stat__label">پشتیبانی آنلاین</div>
			</div>
		</div>
	</div>
</section>

<section class="info-section info-values">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">ارزش‌های ما</h2>
		</header>
		<div class="info-values__list" data-vatan-anim-children>
			<div class="info-value">
				<h4 class="info-value__title">شفافیت کامل</h4>
				<p class="info-value__body">قیمت، شرایط بازگشت، و سیاست‌های پرداخت — همه از روز اول روشن. هیچ هزینه پنهانی، هیچ شگفتی ناخوشایند.</p>
			</div>
			<div class="info-value">
				<h4 class="info-value__title">امنیت پرداخت</h4>
				<p class="info-value__body">پرداخت‌ها از طریق دروازه‌های معتبر اروپایی پردازش می‌شوند. اطلاعات کارت هرگز روی سرور ما ذخیره نمی‌شود.</p>
			</div>
			<div class="info-value">
				<h4 class="info-value__title">احترام به فرهنگ</h4>
				<p class="info-value__body">پلتفرمی ساخته شده توسط فارسی‌زبان‌ها، برای فارسی‌زبان‌ها. تقویم شمسی، RTL، ترجمه دقیق، و درک عمیق از نیازهای جامعه.</p>
			</div>
			<div class="info-value">
				<h4 class="info-value__title">پشتیبانی واقعی</h4>
				<p class="info-value__body">پشتیبان‌های ما خود طرفدار رویدادهای فارسی هستند. می‌فهمند چرا یک بلیت اهمیت دارد.</p>
			</div>
		</div>
	</div>
</section>

<section class="info-cta">
	<div class="container info-cta__inner">
		<h2 class="info-cta__title">آماده‌ای کنسرت بعدی‌ات را تجربه کنی؟</h2>
		<p class="info-cta__lead">بلیت رویدادهای پیش‌رو هنرمندان محبوبت را اکنون رزرو کن.</p>
		<a class="btn btn--primary btn--lg" href="<?php echo esc_url( $events_url ); ?>">مشاهده همه رویدادها</a>
	</div>
</section>
<?php
	$content .= ob_get_clean();
}

/* =============================================================================
 *  CONTACT page
 * ===========================================================================*/

function vatan_sseed_fill_contact( array &$log ): void {
	$id         = vatan_sseed_page_id( 'contact' );
	$hero_image = vatan_sseed_image_url( 'hero-festival-night' );

	ob_start();
	?>
<section class="info-hero">
	<?php if ( $hero_image ) : ?>
		<img class="info-hero__bg" src="<?php echo esc_url( $hero_image ); ?>" alt="" loading="eager" />
	<?php endif; ?>
	<div class="info-hero__overlay" aria-hidden="true"></div>
	<div class="container info-hero__inner">
		<p class="info-hero__eyebrow">تماس با ما</p>
		<h1 class="info-hero__title">به کمک نیاز داری؟ <span class="info-hero__title-accent">ما اینجاییم</span></h1>
		<p class="info-hero__lead">از سؤال درباره بلیت تا همکاری برگزارکنندگان — تیم پشتیبانی وطن ایونت در کمتر از یک روز کاری پاسخ می‌دهد.</p>
	</div>
</section>

<section class="info-section contact-methods">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">روش‌های تماس</h2>
			<p class="info-section__lead">هر کانالی که برایت راحت‌تر است را انتخاب کن.</p>
		</header>
		<div class="contact-methods__grid" data-vatan-anim-children>
			<a class="contact-method" href="https://wa.me/447400000000" target="_blank" rel="noopener">
				<div class="contact-method__icon contact-method__icon--whatsapp">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.5-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.1.2-.3.2-.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.7.4-.2.3-1 1-1 2.4 0 1.4 1 2.7 1.1 2.9.1.2 2 3 4.8 4.2.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.8-.7 2-1.5.2-.7.2-1.4.2-1.5 0-.1-.3-.2-.6-.3zM12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.4 1.3 4.8L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.4c-1.5 0-2.9-.4-4.2-1.1l-.3-.2-3.1.8.8-3-.2-.3c-.8-1.2-1.2-2.7-1.2-4.2 0-4.5 3.7-8.2 8.2-8.2 4.5 0 8.2 3.7 8.2 8.2 0 4.5-3.7 8.2-8.2 8.2z"/></svg>
				</div>
				<h3 class="contact-method__title">واتس‌اپ</h3>
				<p class="contact-method__body">سریع‌ترین راه برای پاسخ. پیامت را بفرست، در کمتر از چند ساعت پاسخ می‌گیری.</p>
				<span class="contact-method__action">+44 7400 000000 ←</span>
			</a>
			<a class="contact-method" href="https://t.me/vatanevent" target="_blank" rel="noopener">
				<div class="contact-method__icon contact-method__icon--telegram">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M9.04 15.84l-.4 5.6c.56 0 .8-.24 1.1-.53l2.64-2.51 5.48 4c1.01.56 1.72.27 1.99-.93l3.6-16.84c.32-1.5-.54-2.08-1.52-1.72L1.06 9.81C-.4 10.38-.38 11.21.81 11.58l5.32 1.66L18.5 5.5c.58-.39 1.11-.18.67.22"/></svg>
				</div>
				<h3 class="contact-method__title">تلگرام</h3>
				<p class="contact-method__body">کانال رسمی ما را دنبال کن: اخبار رویدادها، تخفیف‌ها، و راهنمای خرید بلیت.</p>
				<span class="contact-method__action">@vatanevent ←</span>
			</a>
			<a class="contact-method" href="mailto:support@vatanevent.com">
				<div class="contact-method__icon contact-method__icon--email">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
				</div>
				<h3 class="contact-method__title">ایمیل</h3>
				<p class="contact-method__body">برای پیگیری سفارش، درخواست استرداد، یا همکاری بلندمدت — ایمیل بهترین گزینه است.</p>
				<span class="contact-method__action">support@vatanevent.com ←</span>
			</a>
			<a class="contact-method" href="tel:+442012345678">
				<div class="contact-method__icon contact-method__icon--phone">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
				</div>
				<h3 class="contact-method__title">تلفن</h3>
				<p class="contact-method__body">برای موارد فوری روز رویداد، یا اگر مشکل پرداخت داری، با ما تماس بگیر.</p>
				<span class="contact-method__action">+44 20 1234 5678 ←</span>
			</a>
		</div>
	</div>
</section>

<section class="info-section contact-hours">
	<div class="container info-section__inner">
		<div class="contact-hours__grid">
			<div class="contact-hours__col">
				<h3 class="contact-hours__title">ساعات کاری</h3>
				<dl class="contact-hours__list">
					<div class="contact-hours__row">
						<dt>دوشنبه تا جمعه</dt>
						<dd>۹:۰۰ — ۱۸:۰۰</dd>
					</div>
					<div class="contact-hours__row">
						<dt>شنبه</dt>
						<dd>۱۰:۰۰ — ۱۵:۰۰</dd>
					</div>
					<div class="contact-hours__row">
						<dt>یکشنبه</dt>
						<dd>تعطیل</dd>
					</div>
				</dl>
				<p class="contact-hours__note">واتس‌اپ و ایمیل خارج از ساعات کاری هم پاسخ داده می‌شود — معمولاً ظرف یک روز کاری.</p>
			</div>
			<div class="contact-hours__col">
				<h3 class="contact-hours__title">آدرس دفتر</h3>
				<address class="contact-hours__address">
					Vatan Event Ltd.<br/>
					128 City Road<br/>
					London EC1V 2NX<br/>
					United Kingdom 🇬🇧
				</address>
				<p class="contact-hours__note">دفتر مرکزی — ملاقات حضوری فقط با وقت قبلی.</p>
			</div>
		</div>
	</div>
</section>

<section class="info-cta">
	<div class="container info-cta__inner">
		<h2 class="info-cta__title">سؤالت در سؤالات متداول هست؟</h2>
		<p class="info-cta__lead">قبل از تماس، یک نگاهی به سؤالات متداول بینداز — شاید جوابت آنجا باشد.</p>
		<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">مشاهده FAQ</a>
	</div>
</section>
<?php
	$content = ob_get_clean();
	vatan_sseed_update( $id, 'تماس با ما', $content, $log, 'contact' );
}

/* =============================================================================
 *  SUPPORT page
 * ===========================================================================*/

function vatan_sseed_fill_support( array &$log ): void {
	$id         = vatan_sseed_page_id( 'support' );
	$hero_image = vatan_sseed_image_url( 'event-microphone' );

	$faq_url     = home_url( '/faq/' );
	$contact_url = home_url( '/contact/' );
	$blog_url    = get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' );
	$events_url  = home_url( '/events/' );

	ob_start();
	?>
<section class="info-hero">
	<?php if ( $hero_image ) : ?>
		<img class="info-hero__bg" src="<?php echo esc_url( $hero_image ); ?>" alt="" loading="eager" />
	<?php endif; ?>
	<div class="info-hero__overlay" aria-hidden="true"></div>
	<div class="container info-hero__inner">
		<p class="info-hero__eyebrow">مرکز پشتیبانی</p>
		<h1 class="info-hero__title">چطور می‌توانیم <span class="info-hero__title-accent">کمک کنیم</span>؟</h1>
		<p class="info-hero__lead">سؤالی درباره خرید بلیت، استرداد، یا ثبت رویداد داری؟ احتمالاً پاسخش در یکی از این بخش‌هاست — اگر نبود، فقط با ما تماس بگیر.</p>
	</div>
</section>

<section class="info-section">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">موضوعات محبوب</h2>
			<p class="info-section__lead">یکی از این دسته‌بندی‌ها را انتخاب کن تا راهنماهای مرتبط را ببینی.</p>
		</header>
		<div class="info-pillars__grid" data-vatan-anim-children>
			<a class="info-card support-topic" href="<?php echo esc_url( $blog_url ); ?>?cat=buying-guide">
				<div class="info-card__icon">🎫</div>
				<h3 class="info-card__title">خرید بلیت</h3>
				<p class="info-card__body">از جستجوی رویداد تا پرداخت و دریافت بلیت QR — همه مراحل خرید قدم‌به‌قدم توضیح داده شده.</p>
				<span class="info-card__link">مشاهده راهنماها ←</span>
			</a>
			<a class="info-card support-topic" href="<?php echo esc_url( $blog_url ); ?>?cat=buying-guide">
				<div class="info-card__icon">💸</div>
				<h3 class="info-card__title">استرداد و تغییر</h3>
				<p class="info-card__body">شرایط بازگشت وجه، زمان‌بندی، و نحوه درخواست استرداد بلیت‌های خریداری‌شده.</p>
				<span class="info-card__link">سیاست استرداد ←</span>
			</a>
			<a class="info-card support-topic" href="<?php echo esc_url( $blog_url ); ?>?cat=buying-guide">
				<div class="info-card__icon">📲</div>
				<h3 class="info-card__title">روز رویداد</h3>
				<p class="info-card__body">نمایش کد QR در ورودی، دانلود PDF، چیدمان صندلی، و نکات اضافی برای یک تجربه راحت.</p>
				<span class="info-card__link">آماده‌سازی ←</span>
			</a>
			<a class="info-card support-topic" href="<?php echo esc_url( $blog_url ); ?>?cat=organizer-guide">
				<div class="info-card__icon">🎤</div>
				<h3 class="info-card__title">برگزارکنندگان</h3>
				<p class="info-card__body">ثبت‌نام به‌عنوان برگزارکننده، ساخت رویداد، نقشه صندلی، و دریافت پرداخت پس از فروش.</p>
				<span class="info-card__link">شروع کن ←</span>
			</a>
		</div>
	</div>
</section>

<section class="info-section support-faq">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">پرتکرارترین سؤالات</h2>
		</header>
		<div class="support-faq__list">
			<details class="support-faq__item">
				<summary>چگونه بلیت بخرم؟</summary>
				<p>صفحه رویداد را باز کن، نوع بلیت (و در صورت وجود نقشه صندلی، صندلی) را انتخاب کن، روی «افزودن به سبد خرید» کلیک کن و پرداخت را تکمیل کن. پس از پرداخت، بلیت‌ها در حساب کاربری ← بلیت‌های من نمایش داده می‌شوند.</p>
			</details>
			<details class="support-faq__item">
				<summary>تا چه زمانی می‌توانم بلیت را برگردانم؟</summary>
				<p>تا ۷۲ ساعت پیش از شروع رویداد می‌توانی درخواست استرداد کامل ثبت کنی. درخواست از طریق صفحه سفارش انجام می‌شود و وجه ظرف ۵ تا ۷ روز کاری به همان کارت پرداخت‌کننده برمی‌گردد.</p>
			</details>
			<details class="support-faq__item">
				<summary>چطور بلیت دیجیتال خود را در ورودی نشان دهم؟</summary>
				<p>روز رویداد، وارد بخش «بلیت‌های من» در حساب کاربری شو و کد QR را روی موبایلت نشان بده. به‌عنوان پشتیبان، می‌توانی بلیت را به‌صورت PDF دانلود یا چاپ کنی — حتی بدون اینترنت کار می‌کند.</p>
			</details>
			<details class="support-faq__item">
				<summary>چگونه برگزارکننده شوم و رویداد ثبت کنم؟</summary>
				<p>پس از ساخت حساب کاربری، از داشبورد روی «ایجاد رویداد» کلیک کن. اطلاعات رویداد (تاریخ، سالن، قیمت‌ها، نقشه صندلی) را وارد کن و ارسال کن. تیم ما بررسی می‌کند و در صورت تأیید، بلیت‌فروشی شروع می‌شود.</p>
			</details>
			<details class="support-faq__item">
				<summary>پشتیبانی در چه ساعاتی پاسخگو است؟</summary>
				<p>دوشنبه تا جمعه ۹:۰۰–۱۸:۰۰ به وقت اروپا. درخواست‌های واتس‌اپ و ایمیل خارج از ساعات کاری هم پاسخ داده می‌شود — معمولاً ظرف یک روز کاری.</p>
			</details>
		</div>
		<p class="support-faq__more">
			<a href="<?php echo esc_url( $faq_url ); ?>">مشاهده همه سؤالات متداول ←</a>
		</p>
	</div>
</section>

<section class="info-section">
	<div class="container info-section__inner">
		<header class="info-section__head">
			<h2 class="info-section__title">هنوز جواب پیدا نکردی؟</h2>
		</header>
		<div class="info-pillars__grid" data-vatan-anim-children>
			<a class="info-card" href="<?php echo esc_url( $contact_url ); ?>">
				<div class="info-card__icon">💬</div>
				<h3 class="info-card__title">تماس مستقیم</h3>
				<p class="info-card__body">از طریق واتس‌اپ، تلگرام، یا ایمیل با تیم پشتیبانی در ارتباط باش.</p>
				<span class="info-card__link">صفحه تماس ←</span>
			</a>
			<a class="info-card" href="<?php echo esc_url( $blog_url ); ?>">
				<div class="info-card__icon">📚</div>
				<h3 class="info-card__title">مرور راهنماها</h3>
				<p class="info-card__body">۱۰+ مقاله درباره خرید بلیت، حضور در رویداد، و برگزارکنندگی.</p>
				<span class="info-card__link">مشاهده ←</span>
			</a>
			<a class="info-card" href="<?php echo esc_url( $events_url ); ?>">
				<div class="info-card__icon">🎪</div>
				<h3 class="info-card__title">مرور رویدادها</h3>
				<p class="info-card__body">رویدادهای پیش‌رو در شهرهای مختلف اروپا — همین حالا بلیت رزرو کن.</p>
				<span class="info-card__link">رویدادها ←</span>
			</a>
		</div>
	</div>
</section>
<?php
	$content = ob_get_clean();
	vatan_sseed_update( $id, 'مرکز پشتیبانی', $content, $log, 'support' );
}
