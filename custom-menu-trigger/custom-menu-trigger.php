<?php
/**
 * Plugin Name:       Custom Menu Trigger
 * Description:       Points a theme's existing hamburger button at your own menu panel, with animated open and close, without editing the theme.
 * Version:           1.1.0
 * Requires PHP:      7.4
 * Author:            Salako Halleluyah
 * License:           GPL-2.0-or-later
 * Text Domain:       custom-menu-trigger
 *
 * @package Custom_Menu_Trigger
 *
 * ---------------------------------------------------------------------------
 * THE PROBLEM
 *
 * A client wants a full width designed navigation panel. The theme ships its
 * own hamburger and its own menu, and the theme's menu cannot be made to look
 * like the design.
 *
 * You could fork the theme, which means owning its updates forever. Or you
 * could build the panel you actually want, hide the theme's menu, and point
 * the theme's existing hamburger at yours. That is what this does.
 *
 * Useful detail: the panel can live on a page you keep as a draft, so it never
 * appears in search results or the sitemap. Set 'allow_draft' to true and the
 * panel renders for logged out visitors even though the page is not published.
 *
 * ---------------------------------------------------------------------------
 * CONFIGURING IT
 *
 * Every theme names its hamburger differently, so the selectors are filtered:
 *
 *   add_filter( 'cmt_config', function () {
 *       return array(
 *           'trigger'     => '.my-theme-menu-opener',   // theme's hamburger
 *           'hide'        => '.my-theme-nav-panel',     // theme's own menu
 *           'panel_id'    => 123,                       // page holding your panel
 *           'allow_draft' => true,
 *       );
 *   } );
 *
 * Find the trigger selector with your browser's inspector: click the theme's
 * hamburger, look at what element actually receives the click.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMT_URL', plugin_dir_url( __FILE__ ) );

/**
 * @return array{trigger:string,hide:string,panel_id:int,allow_draft:bool}
 */
function cmt_config() {
	return wp_parse_args(
		(array) apply_filters( 'cmt_config', array() ),
		array(
			'trigger'     => '',
			'hide'        => '',
			'panel_id'    => 0,
			'allow_draft' => false,
		)
	);
}

add_action( 'wp_enqueue_scripts', 'cmt_enqueue' );

function cmt_enqueue() {
	$config = cmt_config();

	if ( ! $config['trigger'] || ! $config['panel_id'] ) {
		return;
	}

	wp_enqueue_style( 'cmt', CMT_URL . 'custom-menu-trigger.css', array(), '1.1.0' );
	wp_enqueue_script( 'cmt', CMT_URL . 'custom-menu-trigger.js', array(), '1.1.0', true );

	wp_localize_script(
		'cmt',
		'cmtConfig',
		array(
			'trigger' => $config['trigger'],
			'hide'    => $config['hide'],
		)
	);

	if ( $config['hide'] ) {
		wp_add_inline_style(
			'cmt',
			sanitize_text_field( $config['hide'] ) . '{display:none;}'
		);
	}
}

add_action( 'wp_footer', 'cmt_render_panel' );

/**
 * Render the panel markup into the footer.
 */
function cmt_render_panel() {
	$config = cmt_config();

	if ( ! $config['panel_id'] ) {
		return;
	}

	$panel = get_post( $config['panel_id'] );

	if ( ! $panel ) {
		return;
	}

	// A draft page is normally invisible to logged out visitors. When the
	// panel is deliberately kept unpublished so it stays out of search
	// results, render it anyway, but only when the site owner opted in.
	if ( 'publish' !== $panel->post_status && ! $config['allow_draft'] ) {
		return;
	}

	$content = apply_filters( 'the_content', $panel->post_content );
	?>
	<div id="cmt-panel" class="cmt-panel" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
		<button type="button" class="cmt-panel__close" aria-label="<?php esc_attr_e( 'Close menu', 'custom-menu-trigger' ); ?>">&times;</button>
		<div class="cmt-panel__inner">
			<?php echo wp_kses_post( $content ); ?>
		</div>
	</div>
	<?php
}
