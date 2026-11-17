<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Shortcode' ) ) {
class WPAgent_Shortcode {
	private $settings;
	private $agents;
	private $repository;
	private $inline_script_added = false;

	public function __construct( WPAgent_Settings $settings, WPAgent_Agents $agents, ?WPAgent_Repository $repository = null ) {
		$this->settings   = $settings;
		$this->agents     = $agents;
		$this->repository = $repository;
	}

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_public_site_assistant' ), 10 );
		add_action( 'admin_footer', array( $this, 'render_admin_assistant' ), 10 );
		add_shortcode( '37agents_chat', array( $this, 'render' ) );
		add_shortcode( 'wpagent_chat', array( $this, 'render' ) );
	}

	public function register_assets() {
		wp_register_script(
			'wpagent-chat',
			WPAGENT_URL . 'assets/js/chat.js',
			array(),
			WPAGENT_VERSION . '.3',
			true
		);

		wp_register_style(
			'wpagent-chat',
			WPAGENT_URL . 'assets/css/chat.css',
			array(),
			WPAGENT_VERSION . '.3'
		);

		wp_register_script(
			'wpagent-steps',
			WPAGENT_URL . 'assets/js/steps.js',
			array(),
			WPAGENT_VERSION . '.3',
			true
		);

		wp_register_style(
			'wpagent-steps',
			WPAGENT_URL . 'assets/css/steps.css',
			array(),
			WPAGENT_VERSION . '.3'
		);

		wp_register_script(
			'wpagent-ead',
			WPAGENT_URL . 'assets/js/ead.js',
			array(),
			WPAGENT_VERSION . '.3',
			true
		);

		wp_register_style(
			'wpagent-ead',
			WPAGENT_URL . 'assets/css/ead.css',
			array(),
			WPAGENT_VERSION . '.3'
		);

		if ( $this->should_enqueue_floating_assets() ) {
			$this->enqueue_chat_assets();
		}
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'agent'  => $this->settings->get( 'agent_slug', 'default' ),
				'title'  => '',
				'layout' => '',
				'theme'  => '',
			),
			$atts,
			'37agents_chat'
		);

		$agent  = $this->agents->get_agent( $atts['agent'] );
		$title  = $atts['title'] ?: $agent['name'];
		$layout = ! empty( $atts['layout'] ) ? sanitize_key( $atts['layout'] ) : ( $agent['frontend_layout'] ?? 'chat' );
		$theme  = sanitize_key( $atts['theme'] ?? '' );
		if ( in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$agent['default_theme'] = $theme;
		}

		// Role-based access check for shortcode display
		$allowed_roles = $agent['allowed_roles'] ?? array();
		if ( ! empty( $allowed_roles ) ) {
			if ( ! is_user_logged_in() ) {
				return '<div class="wpagent-access-notice wpagent-access-notice--login" style="padding:16px 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:16px 0;text-align:center;"><p style="margin:0;font-size:14px;color:#475569;">' .
					esc_html__( 'Você precisa fazer login para acessar este assistente.', 'wpagent' ) .
					' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" style="color:#2563eb;font-weight:600;text-decoration:underline;">' .
					esc_html__( 'Fazer Login', 'wpagent' ) . '</a></p></div>';
			}
			$user = wp_get_current_user();
			$has_role = false;
			foreach ( $allowed_roles as $role ) {
				if ( in_array( $role, (array) $user->roles, true ) ) {
					$has_role = true;
					break;
				}
			}
			if ( ! $has_role ) {
				return '<div class="wpagent-access-notice wpagent-access-notice--denied" style="padding:16px 20px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:16px 0;text-align:center;"><p style="margin:0;font-size:14px;color:#b91c1c;">' .
					esc_html__( 'Você não tem permissão para acessar este assistente.', 'wpagent' ) .
					'</p></div>';
			}
		}

		if ( 'steps' === $layout ) {
			return $this->render_steps( $agent, $title );
		}

		if ( 'ead' === $layout ) {
			return $this->render_ead( $agent, $title );
		}

		return $this->render_chat( $agent, $title );
	}

	public function render_public_site_assistant() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		$agent = $this->agents->get_automatic_agent( 'public_site' );
		if ( ! $agent || '1' !== $agent['allow_guest_chat'] ) {
			return;
		}

		echo $this->render_floating_assistant( $agent, 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_admin_assistant() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$agent = $this->agents->get_automatic_agent( 'admin' );
		if ( ! $agent ) {
			return;
		}

		echo $this->render_floating_assistant( $agent, 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function render_chat( $agent, $title, $extra_class = '' ) {
		$this->enqueue_chat_assets();
		$provider_timeout = max( 15, min( 180, (int) apply_filters( 'wpagent_ai_request_timeout', 90, 'chat' ) ) );
		$browser_timeout  = max( 30000, min( 240000, (int) apply_filters( 'wpagent_chat_request_timeout', ( $provider_timeout + 15 ) * 1000, $agent ) ) );

		$user_id             = get_current_user_id();
		$initial_usage_data  = null;
		$current_usage       = 0;
		$monthly_limit       = 0;
		$enable_global_limit = '1' === $this->settings->get( 'enable_global_token_limit', '0' );

		if ( '1' === ( $agent['show_token_usage'] ?? '0' ) && $this->repository ) {
			$current_monthly    = $this->repository->get_user_monthly_usage( $user_id );
			$current_usage      = (int) ( $current_monthly['total_tokens'] ?? 0 );
			$global_limit       = (int) $this->settings->get( 'global_token_limit', 100000 );
			$monthly_limit      = $enable_global_limit && ! current_user_can( 'manage_options' ) ? $global_limit : 0;
			$initial_usage_data = array(
				'current_monthly' => $current_usage,
				'monthly_limit'   => $monthly_limit,
				'remaining'       => $monthly_limit > 0 ? max( 0, $monthly_limit - $current_usage ) : null,
			);
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( trim( 'wpagent-chat ' . $extra_class ) ); ?>"
			data-agent="<?php echo esc_attr( $agent['slug'] ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat' ) ); ?>"
			data-stream-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat/stream' ) ); ?>"
			data-leads-url="<?php echo esc_url( rest_url( 'wpagent/v1/leads' ) ); ?>"
			data-conversations-url="<?php echo esc_url( rest_url( 'wpagent/v1/conversations' ) ); ?>"
			data-profile-url="<?php echo esc_url( rest_url( 'wpagent/v1/profile' ) ); ?>"
			data-abilities-url="<?php echo esc_url( rest_url( 'wpagent/v1/abilities/run' ) ); ?>"
			data-email-actions-url="<?php echo esc_url( rest_url( 'wpagent/v1/email/send' ) ); ?>"
			data-email-schedules-url="<?php echo esc_url( rest_url( 'wpagent/v1/email/schedule' ) ); ?>"
			data-projects-url="<?php echo esc_url( rest_url( 'wpagent/v1/projects' ) ); ?>"
			data-upload-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat/upload' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-request-timeout="<?php echo esc_attr( $browser_timeout ); ?>"
			data-debug-errors="<?php echo current_user_can( 'manage_options' ) ? '1' : '0'; ?>"
			data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
			data-user-profile-enabled="<?php echo '1' === ( $agent['user_profile_enabled'] ?? '0' ) ? '1' : '0'; ?>"
			data-enable-projects="<?php echo ( is_user_logged_in() && '1' === ( $agent['enable_projects'] ?? '0' ) ) ? '1' : '0'; ?>"
			data-enable-frontend-uploads="<?php echo '1' === ( $agent['enable_frontend_uploads'] ?? '0' ) ? '1' : '0'; ?>"
			data-lead-capture-enabled="<?php echo ( ! is_user_logged_in() && '1' === ( $agent['lead_capture_enabled'] ?? '0' ) ) ? '1' : '0'; ?>"
			data-show-token-usage="<?php echo '1' === ( $agent['show_token_usage'] ?? '0' ) ? '1' : '0'; ?>"
			data-default-theme="<?php echo 'dark' === ( $agent['default_theme'] ?? 'light' ) ? 'dark' : 'light'; ?>"
			data-initial-token-usage="<?php echo esc_attr( wp_json_encode( $initial_usage_data ) ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( WPAgent_I18n::chat_strings() ) ); ?>"
		>
			<?php if ( is_user_logged_in() && '1' === ( $agent['enable_projects'] ?? '0' ) ) : ?>
				<div class="wpagent-chat__projects-bar" data-wpagent-projects-bar>
					<div class="wpagent-chat__projects-tabs" data-wpagent-projects-tabs></div>
					<button type="button" class="wpagent-chat__add-project" data-wpagent-add-project title="<?php esc_attr_e( 'Nova aba / projeto', 'wpagent' ); ?>">+ <?php esc_html_e( 'Novo projeto', 'wpagent' ); ?></button>
				</div>
			<?php endif; ?>
			<div class="wpagent-chat__body">
				<div class="wpagent-chat__mobile-bar">
					<button type="button" class="wpagent-chat__toggle-conversations" aria-expanded="false"><?php esc_html_e( 'Conversas', 'wpagent' ); ?></button>
					<span class="wpagent-chat__mobile-title"><?php echo esc_html( $title ); ?></span>
					<button type="button" class="wpagent-chat__new-conversation wpagent-chat__new-conversation--mobile"><?php esc_html_e( 'Nova', 'wpagent' ); ?></button>
				</div>
				<div class="wpagent-chat__sidebar" aria-label="<?php esc_attr_e( 'Conversas', 'wpagent' ); ?>">
					<div class="wpagent-chat__sidebar-header">
						<div>
							<strong><?php echo esc_html( $title ); ?></strong>
							<span><?php esc_html_e( 'Suas conversas', 'wpagent' ); ?></span>
						</div>
						<button type="button" class="wpagent-chat__new-conversation"><?php esc_html_e( 'Nova', 'wpagent' ); ?></button>
					</div>
					<div class="wpagent-chat__conversations"></div>
					<?php if ( is_user_logged_in() && '1' === ( $agent['user_profile_enabled'] ?? '0' ) ) : ?>
						<div class="wpagent-chat__profile" data-wpagent-profile>
							<label for="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>"><?php echo esc_html( $agent['user_profile_label'] ?: __( 'Sobre voce', 'wpagent' ) ); ?></label>
							<p><?php echo esc_html( $agent['user_profile_description'] ?: __( 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.', 'wpagent' ) ); ?></p>
							<?php $this->render_profile_fields( $agent ); ?>
							<?php if ( ! empty( $agent['user_profile_fields'] ) ) : ?>
								<label class="wpagent-chat__profile-free-label" for="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>"><?php esc_html_e( 'Observacoes adicionais', 'wpagent' ); ?></label>
							<?php endif; ?>
							<textarea id="wpagent-profile-<?php echo esc_attr( $agent['slug'] ); ?>" rows="5" maxlength="4000" data-profile-free placeholder="<?php esc_attr_e( 'Ex.: ano em que leciono, estilo de aula, objetivos e preferencias...', 'wpagent' ); ?>"></textarea>
							<div class="wpagent-chat__profile-actions">
								<span class="wpagent-chat__profile-status" aria-live="polite"></span>
								<button type="button" class="wpagent-chat__profile-save"><?php esc_html_e( 'Salvar perfil', 'wpagent' ); ?></button>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="wpagent-chat__main">
					<div class="wpagent-chat__header">
						<button type="button" class="wpagent-chat__toggle-conversations wpagent-chat__toggle-conversations--desktop" aria-expanded="true"><?php esc_html_e( 'Conversas', 'wpagent' ); ?></button>
						<div class="wpagent-chat__conversation-heading">
							<strong class="wpagent-chat__conversation-title"><?php esc_html_e( 'Nova conversa', 'wpagent' ); ?></strong>
							<span class="wpagent-chat__status"><?php esc_html_e( 'Pronto para conversar', 'wpagent' ); ?></span>
						</div>
						<button type="button" class="wpagent-chat__theme-toggle" aria-pressed="false"><?php esc_html_e( 'Modo escuro', 'wpagent' ); ?></button>
						<button type="button" class="wpagent-chat__rename-conversation"><?php esc_html_e( 'Renomear', 'wpagent' ); ?></button>
					</div>
					<?php echo $this->render_lead_capture_bar( $agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="wpagent-chat__messages" role="log" aria-live="polite">
						<div class="wpagent-chat__empty">
							<strong><?php esc_html_e( 'Como posso ajudar?', 'wpagent' ); ?></strong>
							<span><?php esc_html_e( 'Escreva uma pergunta ou escolha uma conversa anterior.', 'wpagent' ); ?></span>
						</div>
					</div>
					<form class="wpagent-chat__form">
						<label class="screen-reader-text" for="wpagent-message-<?php echo esc_attr( $agent['slug'] ); ?>"><?php esc_html_e( 'Mensagem', 'wpagent' ); ?></label>
						<div class="wpagent-chat__composer">
							<textarea id="wpagent-message-<?php echo esc_attr( $agent['slug'] ); ?>" rows="1" placeholder="<?php esc_attr_e( 'Escreva sua mensagem...', 'wpagent' ); ?>"></textarea>
							<button type="submit"><?php esc_html_e( 'Enviar', 'wpagent' ); ?></button>
						</div>
						<div class="wpagent-chat__hint"><?php esc_html_e( 'Enter envia. Shift + Enter quebra linha.', 'wpagent' ); ?></div>
						<?php echo $this->render_privacy_consent_notice( $agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( '1' === ( $agent['show_token_usage'] ?? '0' ) && $this->repository ) : ?>
							<div class="wpagent-chat__token-usage" data-wpagent-token-usage>
								<div class="wpagent-chat__token-summary">
									<span class="wpagent-chat__token-label"><?php esc_html_e( 'Tokens este mês:', 'wpagent' ); ?></span>
									<span class="wpagent-chat__token-count" data-token-usage-current><?php echo esc_html( number_format_i18n( $current_usage ) ); ?></span>
									<span class="wpagent-chat__token-separator">/</span>
									<span class="wpagent-chat__token-limit" data-token-usage-limit><?php echo $monthly_limit > 0 ? esc_html( number_format_i18n( $monthly_limit ) ) : esc_html__( 'Ilimitado', 'wpagent' ); ?></span>
								</div>
								<?php if ( $monthly_limit > 0 ) : ?>
									<?php $pct = min( 100, max( 0, round( ( $current_usage / $monthly_limit ) * 100 ) ) ); ?>
									<div class="wpagent-chat__token-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( $pct ); ?>" aria-valuemin="0" aria-valuemax="100">
										<span style="width: <?php echo esc_attr( $pct ); ?>%;"></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_profile_fields( $agent ) {
		$fields = is_array( $agent['user_profile_fields'] ?? null ) ? $agent['user_profile_fields'] : array();

		if ( empty( $fields ) ) {
			return;
		}

		echo '<div class="wpagent-chat__profile-fields">';
		foreach ( $fields as $field ) {
			$key = sanitize_key( $field['key'] ?? '' );
			$label = sanitize_text_field( $field['label'] ?? '' );
			$type = sanitize_key( $field['type'] ?? 'text' );
			$placeholder = sanitize_text_field( $field['placeholder'] ?? '' );
			$id = 'wpagent-profile-' . $agent['slug'] . '-' . $key;

			if ( '' === $key || '' === $label ) {
				continue;
			}
			?>
			<div class="wpagent-chat__profile-field">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php if ( 'textarea' === $type ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" rows="3" maxlength="1000" data-profile-key="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
				<?php else : ?>
					<input id="<?php echo esc_attr( $id ); ?>" type="text" maxlength="1000" data-profile-key="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php endif; ?>
			</div>
			<?php
		}
		echo '</div>';
	}

	private function enqueue_chat_assets() {
		wp_enqueue_script( 'wpagent-chat' );
		wp_enqueue_style( 'wpagent-chat' );

		if ( $this->inline_script_added ) {
			return;
		}

		wp_add_inline_script(
			'wpagent-chat',
			'window.wpagentChatI18n = ' . wp_json_encode( WPAgent_I18n::chat_strings() ) . ';',
			'before'
		);

		$this->inline_script_added = true;
	}

	private function should_enqueue_floating_assets() {
		if ( is_admin() ) {
			return current_user_can( 'manage_options' ) && (bool) $this->agents->get_automatic_agent( 'admin' );
		}

		if ( is_user_logged_in() ) {
			return false;
		}

		$agent = $this->agents->get_automatic_agent( 'public_site' );

		return $agent && '1' === $agent['allow_guest_chat'];
	}

	private function render_floating_assistant( $agent, $context ) {
		$title = $agent['name'];

		ob_start();
		?>
		<div class="wpagent-floating-assistant wpagent-floating-assistant--<?php echo esc_attr( $context ); ?>" data-wpagent-floating>
			<button type="button" class="wpagent-floating-assistant__launcher" aria-expanded="false">
				<span class="wpagent-floating-assistant__launcher-icon" aria-hidden="true"></span>
				<span><?php echo esc_html( $title ); ?></span>
			</button>
			<div class="wpagent-floating-assistant__panel" role="dialog" aria-label="<?php echo esc_attr( $title ); ?>">
				<div class="wpagent-floating-assistant__panel-header">
					<strong><?php echo esc_html( $title ); ?></strong>
					<button type="button" class="wpagent-floating-assistant__close" aria-label="<?php esc_attr_e( 'Fechar assistente', 'wpagent' ); ?>"><?php esc_html_e( 'Fechar', 'wpagent' ); ?></button>
				</div>
				<?php echo $this->render_chat( $agent, $title, 'wpagent-chat--floating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_steps( $agent, $title, $extra_class = '' ) {
		wp_enqueue_script( 'wpagent-steps' );
		wp_enqueue_style( 'wpagent-steps' );

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( trim( 'wpagent-steps ' . $extra_class ) ); ?>"
			data-agent="<?php echo esc_attr( $agent['slug'] ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat' ) ); ?>"
			data-leads-url="<?php echo esc_url( rest_url( 'wpagent/v1/leads' ) ); ?>"
			data-progress-url="<?php echo esc_url( rest_url( 'wpagent/v1/progress' ) ); ?>"
			data-report-url="<?php echo esc_url( rest_url( 'wpagent/v1/steps/generate-report' ) ); ?>"
			data-reset-url="<?php echo esc_url( rest_url( 'wpagent/v1/progress/reset' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
			data-lead-capture-enabled="<?php echo ( ! is_user_logged_in() && '1' === ( $agent['lead_capture_enabled'] ?? '0' ) ) ? '1' : '0'; ?>"
			data-button-label="<?php echo esc_attr( $agent['steps_button_label'] ?? __( 'Gerar Relatório Final', 'wpagent' ) ); ?>"
			data-default-theme="<?php echo 'dark' === ( $agent['default_theme'] ?? 'light' ) ? 'dark' : 'light'; ?>"
		>
		<script type="application/json" class="wpagent-steps-config"><?php
			echo wp_json_encode( array(
				'intro'     => $agent['steps_intro'] ?? '',
				'questions' => $agent['steps_questions'] ?? '',
			) );
		?></script>
			<div class="wpagent-steps__header">
				<div class="wpagent-steps__header-info">
					<h2 class="wpagent-steps__title"><?php echo esc_html( $title ); ?></h2>
					<div class="wpagent-steps__header-meta">
						<span class="wpagent-steps__step-count" data-steps-count><?php esc_html_e( 'Carregando etapas...', 'wpagent' ); ?></span>
						<button type="button" class="wpagent-steps__theme-toggle" data-steps-theme-toggle aria-pressed="false" title="<?php esc_attr_e( 'Alternar tema claro/escuro', 'wpagent' ); ?>">
							🌙 <?php esc_html_e( 'Modo escuro', 'wpagent' ); ?>
						</button>
					</div>
				</div>
				<div class="wpagent-steps__progress-track">
					<div class="wpagent-steps__progress-bar" data-steps-progress style="width: 0%;"></div>
				</div>
			</div>

			<div class="wpagent-steps__body">
				<?php echo $this->render_lead_capture_bar( $agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<!-- Card de Introdução (se houver) -->
				<div class="wpagent-steps__intro-card" data-steps-intro-card style="display:none;">
					<div class="wpagent-steps__intro-content" data-steps-intro-text></div>
					<button type="button" class="wpagent-steps__start-btn" data-steps-start><?php esc_html_e( 'Iniciar Passo a Passo ➔', 'wpagent' ); ?></button>
				</div>

				<!-- Card da Pergunta Atual -->
				<div class="wpagent-steps__question-card" data-steps-question-card>
					<div class="wpagent-steps__badge" data-steps-badge><?php esc_html_e( 'Etapa 1', 'wpagent' ); ?></div>
					<h3 class="wpagent-steps__question-title" data-steps-question-title></h3>
					<p class="wpagent-steps__question-tip" data-steps-question-tip></p>

					<div class="wpagent-steps__input-area">
						<textarea class="wpagent-steps__textarea" data-steps-input rows="4" placeholder="<?php esc_attr_e( 'Digite sua resposta aqui...', 'wpagent' ); ?>"></textarea>
						<div class="wpagent-steps__shortcut-hint"><?php esc_html_e( 'Dica: pressione Ctrl + Enter (ou ⌘ + Enter) para avançar rapidamente.', 'wpagent' ); ?></div>
					</div>

					<div class="wpagent-steps__actions">
						<div class="wpagent-steps__actions-left" style="display:flex;gap:8px;align-items:center;">
							<button type="button" class="wpagent-steps__prev-btn" data-steps-prev style="display:none;" title="<?php esc_attr_e( 'Voltar para a etapa anterior', 'wpagent' ); ?>">
								<?php esc_html_e( '⬅️ Voltar', 'wpagent' ); ?>
							</button>
							<button type="button" class="wpagent-steps__skip-btn" data-steps-skip title="<?php esc_attr_e( 'Pular esta pergunta', 'wpagent' ); ?>">
								<?php esc_html_e( '⏭️ Pular pergunta', 'wpagent' ); ?>
							</button>
						</div>
						<button type="button" class="wpagent-steps__next-btn" data-steps-next>
							<?php esc_html_e( 'Próxima Etapa ➔', 'wpagent' ); ?>
						</button>
					</div>
				</div>

				<!-- Tela de Conclusão / Relatório Final -->
				<div class="wpagent-steps__report-card" data-steps-report-card style="display:none;">
					<div class="wpagent-steps__report-header">
						<div class="wpagent-steps__report-badge">🎉 <?php esc_html_e( 'Concluído com Sucesso!', 'wpagent' ); ?></div>
						<h3><?php esc_html_e( 'Relatório Final Gerado', 'wpagent' ); ?></h3>
						<p><?php esc_html_e( 'Confira abaixo o resultado elaborado com base em todas as suas respostas.', 'wpagent' ); ?></p>
					</div>

					<div class="wpagent-steps__report-content" data-steps-report-content></div>

					<div class="wpagent-steps__report-actions">
						<button type="button" class="button button-primary wpagent-steps__copy-btn" data-steps-copy>
							📋 <?php esc_html_e( 'Copiar Relatório', 'wpagent' ); ?>
						</button>
						<button type="button" class="button wpagent-steps__print-btn" data-steps-print>
							🖨️ <?php esc_html_e( 'Imprimir / PDF', 'wpagent' ); ?>
						</button>
						<button type="button" class="button wpagent-steps__restart-btn" data-steps-restart>
							🔄 <?php esc_html_e( 'Refazer Questionário', 'wpagent' ); ?>
						</button>
					</div>
				</div>
				<?php echo $this->render_privacy_consent_notice( $agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_ead( $agent, $title, $extra_class = '' ) {
		wp_enqueue_script( 'wpagent-ead' );
		wp_enqueue_style( 'wpagent-ead' );

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( trim( 'wpagent-ead ' . $extra_class ) ); ?>"
			data-agent="<?php echo esc_attr( $agent['slug'] ); ?>"
			data-course-title="<?php echo esc_attr( $agent['ead_course_title'] ?: $title ); ?>"
			data-syllabus="<?php echo esc_attr( $agent['ead_syllabus'] ?? '' ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'wpagent/v1/chat' ) ); ?>"
			data-progress-url="<?php echo esc_url( rest_url( 'wpagent/v1/progress' ) ); ?>"
			data-lesson-url="<?php echo esc_url( rest_url( 'wpagent/v1/ead/generate-lesson' ) ); ?>"
			data-reset-url="<?php echo esc_url( rest_url( 'wpagent/v1/progress/reset' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>"
			data-default-theme="<?php echo 'dark' === ( $agent['default_theme'] ?? 'light' ) ? 'dark' : 'light'; ?>"
		>
			<div class="wpagent-ead__wrapper">
				<!-- Barra Lateral: Módulos e Aulas -->
				<div class="wpagent-ead__sidebar" data-ead-sidebar>
					<div class="wpagent-ead__sidebar-header">
						<div class="wpagent-ead__sidebar-top">
							<h3 class="wpagent-ead__course-name"><?php echo esc_html( $agent['ead_course_title'] ?: $title ); ?></h3>
							<button type="button" class="wpagent-ead__mobile-drawer-btn" data-ead-drawer-toggle aria-expanded="false">
								📚 <?php esc_html_e( 'Módulos', 'wpagent' ); ?>
							</button>
						</div>
						<div class="wpagent-ead__course-progress">
							<div class="wpagent-ead__progress-label">
								<span><?php esc_html_e( 'Progresso do Curso:', 'wpagent' ); ?></span>
								<strong data-ead-progress-pct>0%</strong>
							</div>
							<div class="wpagent-ead__progress-track">
								<div class="wpagent-ead__progress-bar" data-ead-progress-bar style="width: 0%;"></div>
							</div>
						</div>
					</div>

					<div class="wpagent-ead__modules-list" data-ead-modules>
						<!-- Módulos e aulas renderizados via JS -->
					</div>
				</div>

				<!-- Área Central: Aula e Tutor IA -->
				<div class="wpagent-ead__main">
					<div class="wpagent-ead__lesson-header">
						<div class="wpagent-ead__breadcrumb" data-ead-breadcrumb><?php esc_html_e( 'Selecione uma aula', 'wpagent' ); ?></div>
						<h1 class="wpagent-ead__lesson-title" data-ead-lesson-title></h1>
						<div class="wpagent-ead__header-actions">
							<button type="button" class="wpagent-ead__theme-toggle" data-ead-theme-toggle aria-pressed="false" title="<?php esc_attr_e( 'Alternar tema claro/escuro', 'wpagent' ); ?>">
								🌙 <?php esc_html_e( 'Modo escuro', 'wpagent' ); ?>
							</button>
							<?php if ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ) : ?>
								<button type="button" class="wpagent-ead__regenerate-btn" data-ead-regenerate-btn title="<?php esc_attr_e( 'Regerar conteúdo desta aula com IA', 'wpagent' ); ?>">
									<?php esc_html_e( '🔄 Regerar Conteúdo', 'wpagent' ); ?>
								</button>
							<?php endif; ?>
							<button type="button" class="wpagent-ead__complete-btn" data-ead-complete-btn>
								<?php esc_html_e( '✓ Marcar como Concluída', 'wpagent' ); ?>
							</button>
						</div>
					</div>

					<div class="wpagent-ead__lesson-body">
						<div class="wpagent-ead__content-container" data-ead-lesson-content>
							<div class="wpagent-ead__loading-state"><?php esc_html_e( 'Carregando conteúdo da aula...', 'wpagent' ); ?></div>
						</div>

						<div class="wpagent-ead__navigation">
							<button type="button" class="wpagent-ead__nav-btn wpagent-ead__nav-btn--prev" data-ead-prev-btn disabled>
								⬅ <?php esc_html_e( 'Aula Anterior', 'wpagent' ); ?>
							</button>
							<button type="button" class="wpagent-ead__nav-btn wpagent-ead__nav-btn--next" data-ead-next-btn disabled>
								<?php esc_html_e( 'Próxima Aula', 'wpagent' ); ?> ➡
							</button>
						</div>
					</div>

					<!-- Tutor IA Integrado na Aula -->
					<div class="wpagent-ead__tutor">
						<div class="wpagent-ead__tutor-header">
							<div class="wpagent-ead__tutor-info">
								<span class="wpagent-ead__tutor-avatar">🎓</span>
								<div>
									<strong><?php esc_html_e( 'Tutor IA da Aula', 'wpagent' ); ?></strong>
									<span><?php esc_html_e( 'Tire suas dúvidas ou peça explicações extras sobre esta lição', 'wpagent' ); ?></span>
								</div>
							</div>
						</div>

						<div class="wpagent-ead__tutor-prompts">
							<button type="button" class="wpagent-ead__prompt-chip" data-ead-prompt="<?php esc_attr_e( 'Pode resumir os 3 pontos mais importantes desta aula?', 'wpagent' ); ?>">💡 <?php esc_html_e( 'Resumir pontos-chave', 'wpagent' ); ?></button>
							<button type="button" class="wpagent-ead__prompt-chip" data-ead-prompt="<?php esc_attr_e( 'Dê um exemplo prático do que foi ensinado nesta aula.', 'wpagent' ); ?>">📝 <?php esc_html_e( 'Exemplo prático', 'wpagent' ); ?></button>
							<button type="button" class="wpagent-ead__prompt-chip" data-ead-prompt="<?php esc_attr_e( 'Faça uma pergunta para testar se eu compreendi o conteúdo.', 'wpagent' ); ?>">❓ <?php esc_html_e( 'Testar meu conhecimento', 'wpagent' ); ?></button>
						</div>

						<div class="wpagent-ead__tutor-messages" data-ead-tutor-messages></div>

						<form class="wpagent-ead__tutor-form" data-ead-tutor-form>
							<input type="text" class="wpagent-ead__tutor-input" data-ead-tutor-input placeholder="<?php esc_attr_e( 'Pergunte qualquer coisa sobre a aula atual...', 'wpagent' ); ?>">
							<button type="submit" class="wpagent-ead__tutor-send"><?php esc_html_e( 'Perguntar', 'wpagent' ); ?></button>
						</form>
						<?php echo $this->render_privacy_consent_notice( $agent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_privacy_consent_notice( $agent ) {
		if ( '1' !== ( $agent['privacy_consent_enabled'] ?? '0' ) ) {
			return '';
		}

		$text = trim( (string) ( $agent['privacy_consent_text'] ?? '' ) );
		if ( '' === $text ) {
			$text = __( 'Ao enviar mensagens, você concorda com o processamento por IA conforme nossa Política de Privacidade.', 'wpagent' );
		}

		$policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		ob_start();
		?>
		<div class="wpagent-privacy-consent">
			<span>🔒 <?php echo esc_html( $text ); ?></span>
			<?php if ( ! empty( $policy_url ) ) : ?>
				<a href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Política de Privacidade', 'wpagent' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_lead_capture_bar( $agent ) {
		if ( is_user_logged_in() || '1' !== ( $agent['lead_capture_enabled'] ?? '0' ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="wpagent-lead-bar" data-wpagent-lead-bar>
			<div class="wpagent-lead-bar__title">✉️ <?php esc_html_e( 'Identifique-se para receber acompanhamento', 'wpagent' ); ?></div>
			<div class="wpagent-lead-bar__fields">
				<input type="text" class="wpagent-lead-bar__input" data-lead-name placeholder="<?php esc_attr_e( 'Seu nome', 'wpagent' ); ?>" maxlength="120">
				<input type="email" class="wpagent-lead-bar__input" data-lead-email placeholder="<?php esc_attr_e( 'Seu melhor e-mail', 'wpagent' ); ?>" maxlength="190">
				<button type="button" class="wpagent-lead-bar__submit" data-lead-submit><?php esc_html_e( 'Continuar', 'wpagent' ); ?></button>
			</div>
			<div class="wpagent-lead-bar__feedback" data-lead-feedback style="display:none;"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
}
