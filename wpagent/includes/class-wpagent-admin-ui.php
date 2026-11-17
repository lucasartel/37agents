<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Admin_UI' ) ) {
class WPAgent_Admin_UI {

	public static function render_header( $title = '', $subtitle = '' ) {
		$logo_url = plugins_url( 'assets/brand/wpagents-logo.png', dirname( __FILE__ ) );
		?>
		<div class="wpagents-admin-wrap">
			<div class="wpagents-admin-header">
				<div class="wpagents-admin-header-left">
					<div class="wpagents-admin-header-logo">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent' ) ); ?>">
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="37agents">
						</a>
					</div>
					<div>
						<?php if ( ! empty( $title ) ) : ?>
							<h1 class="wpagents-admin-header-title"><?php echo esc_html( $title ); ?></h1>
						<?php endif; ?>
						<?php if ( ! empty( $subtitle ) ) : ?>
							<p class="wpagents-admin-header-subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="wpagents-admin-header-right">
					<span class="wpagents-badge-version">v<?php echo esc_html( WPAGENT_VERSION ); ?></span>
					<a href="https://37graus.com.br/wp-agents" target="_blank" rel="noopener noreferrer" class="wpagents-btn-premium">
						<span>📖 <?php esc_html_e( 'Suporte & Documentação', 'wpagent' ); ?></span>
					</a>
				</div>
			</div>
		<?php
	}

	public static function render_footer() {
		?>
		</div><!-- .wpagents-admin-wrap -->
		<?php
	}

	public static function enqueue_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'wpagent' ) && 'wpagent_agent' !== (string) $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'wpagents-admin-style',
			plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
			array(),
			WPAGENT_VERSION
		);

		wp_enqueue_script(
			'wpagents-admin-script',
			plugins_url( 'assets/js/admin.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			WPAGENT_VERSION,
			true
		);
	}
}
}
