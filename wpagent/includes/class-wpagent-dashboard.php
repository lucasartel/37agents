<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Dashboard' ) ) {
class WPAgent_Dashboard {

	public function register() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_no_provider_notice' ) );
			add_action( 'wp_ajax_wpagent_dismiss_no_provider', array( $this, 'ajax_dismiss_notice' ) );
		}
	}

	public function enqueue_admin_assets() {
		WPAgent_Admin_UI::enqueue_styles();
	}

	public function maybe_show_no_provider_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), 'wpagent_dismiss_no_provider', true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'wpagent' ) ) {
			return;
		}
		$wp_ai_available  = function_exists( 'wp_ai_client_prompt' ) || function_exists( 'WordPress\\AI\\get_ai_service' );
		$settings         = get_option( 'wpagent_settings', array() );
		$openrouter_key   = ! empty( $settings['openrouter_api_key'] );
		if ( $wp_ai_available || $openrouter_key ) {
			return;
		}
		$settings_url = admin_url( 'admin.php?page=wpagent-settings' );
		$dismiss_url  = wp_nonce_url( admin_url( 'admin-ajax.php?action=wpagent_dismiss_no_provider' ), 'wpagent_dismiss_no_provider' );
		echo '<div class="notice notice-warning is-dismissible" id="wpagent-no-provider-notice"><p>';
		printf(
			/* translators: 1: settings URL */
			wp_kses(
				__( '<strong>WPAgents:</strong> Nenhum provedor de IA configurado. Configure a chave OpenRouter ou ative o WordPress AI Client em <a href="%1$s">Configurações</a>.', 'wpagent' ),
				array( 'strong' => array(), 'a' => array( 'href' => array() ) )
			),
			esc_url( $settings_url )
		);
		echo '</p></div>';
	}

	public function ajax_dismiss_notice() {
		check_ajax_referer( 'wpagent_dismiss_no_provider' );
		if ( current_user_can( 'manage_options' ) ) {
			update_user_meta( get_current_user_id(), 'wpagent_dismiss_no_provider', '1' );
		}
		wp_die();
	}

	private function get_provider_status() {
		$wp_ai_available = function_exists( 'wp_ai_client_prompt' ) || function_exists( 'WordPress\\AI\\get_ai_service' );
		$settings        = get_option( 'wpagent_settings', array() );
		$openrouter_key  = ! empty( $settings['openrouter_api_key'] );

		if ( $wp_ai_available ) {
			return array(
				'status' => 'wp_ai',
				'label'  => __( 'WordPress AI Client', 'wpagent' ),
				'icon'   => '🔗',
				'color'  => '#1a73e8',
				'desc'   => __( 'Usando conectores nativos do WordPress AI. OpenRouter disponível como fallback.', 'wpagent' ),
			);
		} elseif ( $openrouter_key ) {
			return array(
				'status' => 'openrouter',
				'label'  => __( 'OpenRouter', 'wpagent' ),
				'icon'   => '⚡',
				'color'  => '#6c47ff',
				'desc'   => __( 'Usando OpenRouter como provedor principal. Acesso a centenas de modelos de IA.', 'wpagent' ),
			);
		} else {
			return array(
				'status' => 'none',
				'label'  => __( 'Nenhum provedor configurado', 'wpagent' ),
				'icon'   => '⚠️',
				'color'  => '#d93025',
				'desc'   => __( 'Configure a chave OpenRouter ou ative o WordPress AI Client para os agentes funcionarem.', 'wpagent' ),
			);
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$provider = $this->get_provider_status();

		WPAgent_Admin_UI::render_header(
			__( 'Dashboard Central WPAgents', 'wpagent' ),
			__( 'Visao geral, acessos rapidos aos modulos de IA e suporte oficial.', 'wpagent' )
		);
		?>
		<div style="margin-bottom:16px;padding:14px 18px;background:#fff;border:1px solid #ddd;border-left:4px solid <?php echo esc_attr( $provider['color'] ); ?>;border-radius:6px;display:flex;align-items:center;gap:12px;">
			<span style="font-size:22px;"><?php echo esc_html( $provider['icon'] ); ?></span>
			<div>
				<strong><?php esc_html_e( 'Provedor de IA ativo:', 'wpagent' ); ?> <?php echo esc_html( $provider['label'] ); ?></strong>
				<p style="margin:2px 0 0;color:#555;font-size:13px;"><?php echo esc_html( $provider['desc'] ); ?></p>
			</div>
			<?php if ( 'none' !== $provider['status'] ) : ?>
			<span style="margin-left:auto;background:<?php echo esc_attr( $provider['color'] ); ?>;color:#fff;padding:3px 12px;border-radius:12px;font-size:12px;font-weight:600;"><?php esc_html_e( 'Ativo', 'wpagent' ); ?></span>
			<?php else : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-settings' ) ); ?>" style="margin-left:auto;background:#d93025;color:#fff;padding:3px 12px;border-radius:12px;font-size:12px;font-weight:600;text-decoration:none;"><?php esc_html_e( 'Configurar →', 'wpagent' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="wpagents-dashboard-grid">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpagent_agent' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">🤖</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Agentes', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Crie e treine agentes com documentos, base de conhecimento exclusiva e prompts especializados.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Gerenciar Agentes →', 'wpagent' ); ?></span>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-conversation-summaries' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">💬</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Atendimentos', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Histórico completo de conversas dos seus visitantes com resumos e insights automáticos por IA.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Ver Atendimentos →', 'wpagent' ); ?></span>
			</a>

			<?php /*
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-email-schedules' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">📧</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Agendamentos', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Modo Mailer e Drip: construa sequências de e-mails automatizadas, capture leads e nutra relacionamentos.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Abrir Mailer Drip →', 'wpagent' ); ?></span>
			</a>
			*/ ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-reports' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">📊</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Relatórios', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Acompanhe métricas de desempenho, volume de interações, conversões e taxa de engajamento.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Visualizar Métricas →', 'wpagent' ); ?></span>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-token-usage' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">⚡</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Uso de Tokens', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Monitoramento em tempo real do consumo de tokens OpenAI/Gemini e gestão de custos por modelo.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Verificar Consumo →', 'wpagent' ); ?></span>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-periodic-tasks' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">📰</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Conteúdo Periódico', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Agende seus agentes para redigirem artigos de blog em modo rascunho com revisão editorial.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Rotinas de Conteúdo →', 'wpagent' ); ?></span>
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-settings' ) ); ?>" class="wpagents-dashboard-card">
				<div>
					<div class="wpagents-dashboard-card-icon">⚙️</div>
					<h3 class="wpagents-dashboard-card-title"><?php esc_html_e( 'Configurações', 'wpagent' ); ?></h3>
					<p class="wpagents-dashboard-card-desc"><?php esc_html_e( 'Conectores de Inteligência Artificial, chaves de API, segurança e parâmetros do sistema.', 'wpagent' ); ?></p>
				</div>
				<span class="wpagents-dashboard-card-link"><?php esc_html_e( 'Ajustar Conectores →', 'wpagent' ); ?></span>
			</a>
		</div>

		<div class="wpagents-support-banner">
			<div class="wpagents-support-content">
				<h3><?php esc_html_e( 'Potencialize seus Agentes com Configuração Sob Medida & Suporte Premium', 'wpagent' ); ?></h3>
				<p><?php esc_html_e( 'Com o plano de anuidade oficial do WPAgents, você garante suporte técnico prioritário, consultoria para curadoria dos seus agentes e sequências do Mailer, além de atualizações e novas habilidades de IA para o seu WordPress.', 'wpagent' ); ?></p>
			</div>
			<div class="wpagents-support-actions">
				<a href="https://37graus.com.br/wp-agents" target="_blank" class="wpagents-btn-light">
					<span>🚀 <?php esc_html_e( 'Conhecer Anuidade Oficial', 'wpagent' ); ?></span>
				</a>
			</div>
		</div>
		<?php
		WPAgent_Admin_UI::render_footer();
	}
}
}
