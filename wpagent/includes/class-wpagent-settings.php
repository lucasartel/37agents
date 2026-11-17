<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Settings' ) ) {
class WPAgent_Settings {
	const OPTION_NAME = 'wpagent_settings';

	public static function get_compatible_provider_presets() {
		return array(
			'xiaomi_token_plan' => array(
				'label'         => 'Xiaomi MiMo — Token Plan (Assinatura tp-... / ttp-...)',
				'base_url'      => 'https://token-plan-cn.xiaomimimo.com/v1',
				'default_model' => 'mimo-v2.6-pro',
				'models'        => array( 'mimo-v2.6-pro', 'mimo-v2.6-flash' ),
				'key_hint'      => __( 'Chave do plano iniciada por tp-... (individual) ou ttp-... (equipe) em platform.xiaomimimo.com', 'wpagent' ),
			),
			'xiaomi_mimo' => array(
				'label'         => 'Xiaomi MiMo — API Padrão (sk-...)',
				'base_url'      => 'https://api.xiaomimimo.com/v1',
				'default_model' => 'mimo-v2.6-pro',
				'models'        => array( 'mimo-v2.6-pro', 'mimo-v2.6-flash' ),
				'key_hint'      => __( 'Chave de API padrão (sk-...) gerada em platform.xiaomimimo.com', 'wpagent' ),
			),
			'alibaba_coding' => array(
				'label'         => 'Alibaba Cloud — Qwen / Bailian Coding Plan (Assinatura)',
				'base_url'      => 'https://coding.dashscope.aliyuncs.com/v1',
				'default_model' => 'qwen3.5-plus',
				'models'        => array( 'qwen3.5-plus', 'qwen3-coder-plus', 'qwen3.8-max', 'qwen-max', 'qwen-plus' ),
				'key_hint'      => __( 'Chave exclusiva do Coding Plan no console Alibaba Cloud Bailian / DashScope', 'wpagent' ),
			),
			'alibaba_dashscope' => array(
				'label'         => 'Alibaba Cloud — DashScope / Qwen API (Internacional)',
				'base_url'      => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
				'default_model' => 'qwen-plus',
				'models'        => array( 'qwen-max', 'qwen-plus', 'qwen-turbo', 'qwen3-coder-plus' ),
				'key_hint'      => __( 'Chave DashScope (sk-...)', 'wpagent' ),
			),
			'zhipu_coding' => array(
				'label'         => 'Zhipu AI (BigModel) — GLM Coding Plan (Assinatura)',
				'base_url'      => 'https://open.bigmodel.cn/api/coding/paas/v4',
				'default_model' => 'glm-5.3',
				'models'        => array( 'glm-5.3', 'glm-5.3-flash', 'glm-4.7', 'glm-4-plus', 'glm-4-flash' ),
				'key_hint'      => __( 'Chave do GLM Coding Plan em open.bigmodel.cn', 'wpagent' ),
			),
			'zhipu_zai' => array(
				'label'         => 'Zhipu AI (Z.ai) — GLM Internacional / API Padrão',
				'base_url'      => 'https://api.z.ai/api/paas/v4',
				'default_model' => 'glm-5.3',
				'models'        => array( 'glm-5.3', 'glm-5.3-flash', 'glm-4.7', 'glm-4-plus', 'glm-4-flash' ),
				'key_hint'      => __( 'Chave de API Z.ai (api.z.ai) ou BigModel', 'wpagent' ),
			),
			'kimi_coding' => array(
				'label'         => 'Moonshot AI — Kimi Code Plan (Assinatura Global)',
				'base_url'      => 'https://api.kimi.ai/coding/v1',
				'default_model' => 'kimi-k2.7-code',
				'models'        => array( 'kimi-k2.7-code', 'kimi-k3', 'kimi-k3.1-pro' ),
				'key_hint'      => __( 'Chave de assinatura do Kimi Code (kimi.ai / kimi.com)', 'wpagent' ),
			),
			'kimi_coding_cn' => array(
				'label'         => 'Moonshot AI — Kimi Code Plan (Assinatura China)',
				'base_url'      => 'https://api.kimi.com/coding/v1',
				'default_model' => 'kimi-k2.7-code',
				'models'        => array( 'kimi-k2.7-code', 'kimi-k3', 'kimi-k3.1-pro' ),
				'key_hint'      => __( 'Chave de assinatura do Kimi Code China (kimi.com)', 'wpagent' ),
			),
			'moonshot_kimi' => array(
				'label'         => 'Moonshot AI — Kimi API Padrão',
				'base_url'      => 'https://api.moonshot.ai/v1',
				'default_model' => 'moonshot-v1-auto',
				'models'        => array( 'moonshot-v1-auto', 'kimi-k3', 'moonshot-v1-128k' ),
				'key_hint'      => __( 'Chave da plataforma Moonshot AI (platform.moonshot.ai)', 'wpagent' ),
			),
			'minimax' => array(
				'label'         => 'MiniMax — M Plan / Coding Plan (Assinatura & API)',
				'base_url'      => 'https://api.minimaxi.com/v1',
				'default_model' => 'MiniMax-M3',
				'models'        => array( 'MiniMax-M3', 'MiniMax-M3.1-Flash', 'MiniMax-M2.5', 'MiniMax-Text-01' ),
				'key_hint'      => __( 'Chave do MiniMax M Plan / API (minimaxi.com)', 'wpagent' ),
			),
			'volcengine_ark' => array(
				'label'         => 'Volcengine (ByteDance) — Volcano Ark / Doubao Coding Plan',
				'base_url'      => 'https://ark.cn-beijing.volces.com/api/v3',
				'default_model' => 'doubao-seed-1-6-coding',
				'models'        => array( 'doubao-seed-1-6-coding', 'doubao-1-5-pro-32k-250115' ),
				'key_hint'      => __( 'Chave de API do Volcano Ark (informe o nome do modelo ou Endpoint ID ep-...)', 'wpagent' ),
			),
			'deepseek' => array(
				'label'         => 'DeepSeek — API Oficial',
				'base_url'      => 'https://api.deepseek.com/v1',
				'default_model' => 'deepseek-chat',
				'models'        => array( 'deepseek-chat', 'deepseek-reasoner' ),
				'key_hint'      => __( 'Chave da plataforma DeepSeek (platform.deepseek.com)', 'wpagent' ),
			),
			'siliconflow' => array(
				'label'         => 'SiliconFlow — Cloud AI',
				'base_url'      => 'https://api.siliconflow.com/v1',
				'default_model' => 'deepseek-ai/DeepSeek-V3',
				'models'        => array( 'deepseek-ai/DeepSeek-V3', 'Qwen/Qwen3-235B-A22B', 'THUDM/GLM-4-32B-0414' ),
				'key_hint'      => __( 'Chave de API SiliconFlow (cloud.siliconflow.com)', 'wpagent' ),
			),
			'custom' => array(
				'label'         => __( 'Outro Endpoint Compatível com OpenAI / OpenCode (Personalizado)', 'wpagent' ),
				'base_url'      => '',
				'default_model' => '',
				'models'        => array(),
				'key_hint'      => __( 'Qualquer provedor ou assinatura que siga o protocolo OpenAI (/v1/chat/completions)', 'wpagent' ),
			),
		);
	}

	public static function render_compatible_model_datalist( $selected_preset = '' ) {
		$presets    = self::get_compatible_provider_presets();
		$models     = array();
		$all_models = array();

		foreach ( $presets as $preset ) {
			foreach ( (array) ( $preset['models'] ?? array() ) as $model_id ) {
				$all_models[ $model_id ] = $model_id;
			}
		}

		if ( ! empty( $selected_preset ) && isset( $presets[ $selected_preset ]['models'] ) ) {
			foreach ( $presets[ $selected_preset ]['models'] as $model_id ) {
				$models[ $model_id ] = $model_id;
			}
		}

		$models = $models + $all_models;
		?>
		<datalist id="wpagent-compatible-model-presets">
			<?php foreach ( $models as $model_id ) : ?>
				<option value="<?php echo esc_attr( $model_id ); ?>">
			<?php endforeach; ?>
		</datalist>
		<?php
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_menu', array( $this, 'reorder_admin_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'parent_file', array( $this, 'fix_parent_menu_link' ), 999 );
	}

	public function add_menu() {
		$dashboard_callback = class_exists( 'WPAgent_Dashboard' ) ? array( new WPAgent_Dashboard(), 'render_page' ) : array( $this, 'render_page' );

		$icon_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0IiBmaWxsPSJibGFjayI+PHBhdGggZD0iTTEwIDJsMS45IDUuN0wxOCA5LjZsLTYuMSAxLjlMMTAgMTcuMiA4LjEgMTEuNSAyIDkuNmw2LjEtMS45TDEwIDJ6bTEwIDEwbC45IDIuOEwyNCAxNS44bC0zLjEuOUwyMCAxOS41bC0uOS0yLjgtMy4xLS45IDMuMS0uOUwyMCAxMnptLTMgN2wuNiAxLjggMS45LjYtMS45LjYtLjYgMS44LS42LTEuOC0xLjktLjYgMS45LS42LjYtMS44eiIvPjwvc3ZnPg==';

		add_menu_page(
			__( '37agents', 'wpagent' ),
			__( '37agents', 'wpagent' ),
			'manage_options',
			'wpagent',
			$dashboard_callback,
			$icon_svg,
			58
		);

		add_submenu_page(
			'wpagent',
			__( 'Dashboard', 'wpagent' ),
			__( 'Dashboard', 'wpagent' ),
			'manage_options',
			'wpagent',
			$dashboard_callback
		);

		add_submenu_page(
			'wpagent',
			__( 'Configurações', 'wpagent' ),
			__( 'Configurações', 'wpagent' ),
			'manage_options',
			'wpagent-settings',
			array( $this, 'render_page' )
		);
	}

	public function reorder_admin_menu() {
		global $submenu;
		if ( ! isset( $submenu['wpagent'] ) || ! is_array( $submenu['wpagent'] ) ) {
			return;
		}

		$desired_order = array(
			'wpagent',                          // Dashboard
			'edit.php?post_type=wpagent_agent', // Agentes
			'wpagent-conversation-summaries',   // Atendimentos
			'wpagent-comment-moderation',       // Moderação de Comentários (v0.6.1)
			// 'wpagent-email-schedules',          // Agendamentos (desativado na v0.5.9)
			'wpagent-periodic-tasks',           // Conteúdo Periódico
			'wpagent-reports',                  // Relatórios
			'wpagent-token-usage',              // Uso de Tokens
			'wpagent-settings',                 // Configurações
		);

		$current = $submenu['wpagent'];
		$ordered = array();

		foreach ( $desired_order as $slug ) {
			foreach ( $current as $key => $item ) {
				if ( isset( $item[2] ) && $item[2] === $slug ) {
					$ordered[] = $item;
					unset( $current[ $key ] );
					break;
				}
			}
		}

		foreach ( $current as $item ) {
			$ordered[] = $item;
		}

		$submenu['wpagent'] = array_values( $ordered );

		// Garante que a primeira opção seja sempre o Dashboard ('wpagent') para o menu raiz apontar para o Dashboard e não Agentes
		if ( isset( $submenu['wpagent'][0] ) && $submenu['wpagent'][0][2] !== 'wpagent' ) {
			foreach ( $submenu['wpagent'] as $k => $subitem ) {
				if ( isset( $subitem[2] ) && 'wpagent' === $subitem[2] ) {
					$dashboard_item = $submenu['wpagent'][ $k ];
					unset( $submenu['wpagent'][ $k ] );
					array_unshift( $submenu['wpagent'], $dashboard_item );
					$submenu['wpagent'] = array_values( $submenu['wpagent'] );
					break;
				}
			}
		}

		global $menu;
		if ( ! empty( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $idx => $m_item ) {
				if ( isset( $m_item[2] ) && 'wpagent' === $m_item[2] ) {
					$menu[ $idx ][2] = 'wpagent';
					break;
				}
			}
		}
	}

	public function fix_parent_menu_link( $parent_file ) {
		global $submenu;
		if ( isset( $submenu['wpagent'] ) && is_array( $submenu['wpagent'] ) ) {
			$submenu['wpagent'] = array_values( $submenu['wpagent'] );
			if ( isset( $submenu['wpagent'][0] ) && $submenu['wpagent'][0][2] !== 'wpagent' ) {
				foreach ( $submenu['wpagent'] as $k => $item ) {
					if ( isset( $item[2] ) && 'wpagent' === $item[2] ) {
						$dashboard = $submenu['wpagent'][ $k ];
						unset( $submenu['wpagent'][ $k ] );
						array_unshift( $submenu['wpagent'], $dashboard );
						$submenu['wpagent'] = array_values( $submenu['wpagent'] );
						break;
					}
				}
			}
		}
		return $parent_file;
	}

	public function register_settings() {
		register_setting(
			'wpagent_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		$current            = $this->all();
		$raw_stored_options = get_option( self::OPTION_NAME, array() );
		$raw_stored_options = is_array( $raw_stored_options ) ? $raw_stored_options : array();

		$submitted_key = isset( $input['openrouter_api_key'] ) ? trim( (string) $input['openrouter_api_key'] ) : '';
		$raw_key       = '' === $submitted_key
			? sanitize_text_field( $raw_stored_options['openrouter_api_key'] ?? '' )
			: sanitize_text_field( $submitted_key );

		$submitted_compat_key = isset( $input['compatible_api_key'] ) ? trim( (string) $input['compatible_api_key'] ) : '';
		$raw_compat_key       = '' === $submitted_compat_key
			? sanitize_text_field( $raw_stored_options['compatible_api_key'] ?? '' )
			: sanitize_text_field( $submitted_compat_key );

		$allowed_modes = array( 'wordpress_ai', 'wpagent_compatible', 'wpagent_openrouter' );
		if ( isset( $input['default_provider_mode'] ) && in_array( $input['default_provider_mode'], $allowed_modes, true ) ) {
			$default_provider_mode = $input['default_provider_mode'];
		} else {
			$default_provider_mode = empty( $input['use_wp_ai_client'] ) ? ( $current['default_provider_mode'] ?? 'wpagent_openrouter' ) : 'wordpress_ai';
		}

		$presets           = self::get_compatible_provider_presets();
		$compatible_preset = sanitize_key( $input['compatible_provider_preset'] ?? $current['compatible_provider_preset'] ?? 'xiaomi_token_plan' );
		if ( ! isset( $presets[ $compatible_preset ] ) ) {
			$compatible_preset = 'xiaomi_token_plan';
		}

		$compatible_base_url = esc_url_raw( trim( (string) ( $input['compatible_api_base_url'] ?? $current['compatible_api_base_url'] ?? '' ) ) );
		if ( '' === $compatible_base_url && 'custom' !== $compatible_preset ) {
			$compatible_base_url = $presets[ $compatible_preset ]['base_url'];
		}

		$compatible_model = sanitize_text_field( $input['compatible_model'] ?? $current['compatible_model'] ?? '' );
		if ( '' === $compatible_model && 'custom' !== $compatible_preset ) {
			$compatible_model = $presets[ $compatible_preset ]['default_model'];
		}

		$output = array(
			'agent_name'                 => sanitize_text_field( $input['agent_name'] ?? $current['agent_name'] ?? '37agents' ),
			'agent_slug'                 => sanitize_key( $input['agent_slug'] ?? $current['agent_slug'] ?? 'default' ),
			'default_provider_mode'      => $default_provider_mode,
			'compatible_provider_preset' => $compatible_preset,
			'compatible_api_base_url'    => $compatible_base_url,
			'compatible_api_key'         => $this->encrypt_secret( $raw_compat_key ),
			'compatible_model'           => $compatible_model,
			'openrouter_api_key'         => $this->encrypt_secret( $raw_key ),
			'openrouter_model'           => sanitize_text_field( $input['openrouter_model'] ?? $current['openrouter_model'] ?? 'openai/gpt-4.1-mini' ),
			'wordpress_ai_provider'      => sanitize_key( $input['wordpress_ai_provider'] ?? $current['wordpress_ai_provider'] ?? '' ),
			'wordpress_ai_model'         => sanitize_text_field( $input['wordpress_ai_model'] ?? $current['wordpress_ai_model'] ?? '' ),
			'use_wp_ai_client'           => 'wordpress_ai' === $default_provider_mode ? '1' : '0',
			'allow_guest_chat'           => empty( $input['allow_guest_chat'] ) ? '0' : '1',
			'max_knowledge_items'        => max( 0, absint( $input['max_knowledge_items'] ?? 4 ) ),
			'max_memory_items'           => max( 0, absint( $input['max_memory_items'] ?? 8 ) ),
			'max_recent_interactions'    => max( 0, absint( $input['max_recent_interactions'] ?? 4 ) ),
			'ai_request_timeout'         => max( 5, min( 300, absint( $input['ai_request_timeout'] ?? 30 ) ) ),
			'embedding_enabled'          => empty( $input['embedding_enabled'] ) ? '0' : '1',
			'embedding_provider'         => sanitize_key( $input['embedding_provider'] ?? $current['embedding_provider'] ?? 'openrouter' ),
			'embedding_model'            => sanitize_text_field( $input['embedding_model'] ?? $current['embedding_model'] ?? 'openai/text-embedding-3-small' ),
			'embedding_batch_size'       => max( 1, min( 50, absint( $input['embedding_batch_size'] ?? 10 ) ) ),
			'system_prompt'              => wp_kses_post( $input['system_prompt'] ?? $current['system_prompt'] ?? '' ),
			'enable_global_token_limit'  => empty( $input['enable_global_token_limit'] ) ? '0' : '1',
			'global_token_limit'         => max( 0, absint( $input['global_token_limit'] ?? 100000 ) ),
		);

		return $output;
	}

	private function encryption_key() {
		$material = ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' ) . '|' . ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : '' );
		if ( '|' === $material && function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'auth' );
		}
		return hash( 'sha256', $material, true );
	}

	private function encrypt_secret( $plain ) {
		$plain = trim( (string) $plain );
		if ( '' === $plain || 0 === strpos( $plain, 'enc:v1:' ) ) {
			return $plain;
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}
		$iv = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $this->encryption_key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plain;
		}
		return 'enc:v1:' . base64_encode( $iv . $cipher );
	}

	private function decrypt_secret( $stored ) {
		$stored = trim( (string) $stored );
		if ( '' === $stored || 0 !== strpos( $stored, 'enc:v1:' ) ) {
			return $stored;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$decoded = base64_decode( substr( $stored, 7 ), true );
		if ( false === $decoded || strlen( $decoded ) <= 16 ) {
			return '';
		}
		$iv = substr( $decoded, 0, 16 );
		$cipher = substr( $decoded, 16 );
		$plain = openssl_decrypt( $cipher, 'AES-256-CBC', $this->encryption_key(), OPENSSL_RAW_DATA, $iv );
		return false !== $plain ? $plain : '';
	}

	public function all() {
		$defaults = array(
			'agent_name'                 => '37agents',
			'agent_slug'                 => 'default',
			'default_provider_mode'      => '',
			'compatible_provider_preset' => 'xiaomi_token_plan',
			'compatible_api_base_url'    => 'https://token-plan-cn.xiaomimimo.com/v1',
			'compatible_api_key'         => '',
			'compatible_model'           => 'mimo-v2.6-pro',
			'openrouter_api_key'         => '',
			'openrouter_model'           => 'openai/gpt-4.1-mini',
			'wordpress_ai_provider'      => '',
			'wordpress_ai_model'         => '',
			'use_wp_ai_client'           => '1',
			'allow_guest_chat'           => '0',
			'max_knowledge_items'        => 4,
			'max_memory_items'           => 8,
			'max_recent_interactions'    => 4,
			'embedding_enabled'          => '0',
			'embedding_provider'         => 'openrouter',
			'embedding_model'            => 'openai/text-embedding-3-small',
			'embedding_batch_size'       => 10,
			'system_prompt'              => 'Voce e um assistente pessoal cuidadoso, util e contextual.',
			'enable_global_token_limit'  => '0',
			'global_token_limit'         => 100000,
		);

		$options = get_option( self::OPTION_NAME, array() );
		$parsed  = wp_parse_args( is_array( $options ) ? $options : array(), $defaults );

		if ( empty( $parsed['default_provider_mode'] ) ) {
			$parsed['default_provider_mode'] = '1' === (string) ( $parsed['use_wp_ai_client'] ?? '1' ) ? 'wordpress_ai' : 'wpagent_openrouter';
		}

		if ( defined( 'WPAGENT_OPENROUTER_API_KEY' ) && '' !== trim( (string) WPAGENT_OPENROUTER_API_KEY ) ) {
			$parsed['openrouter_api_key'] = trim( (string) WPAGENT_OPENROUTER_API_KEY );
		} elseif ( ! empty( $parsed['openrouter_api_key'] ) ) {
			$parsed['openrouter_api_key'] = $this->decrypt_secret( $parsed['openrouter_api_key'] );
		}

		if ( defined( 'WPAGENT_COMPATIBLE_API_KEY' ) && '' !== trim( (string) WPAGENT_COMPATIBLE_API_KEY ) ) {
			$parsed['compatible_api_key'] = trim( (string) WPAGENT_COMPATIBLE_API_KEY );
		} elseif ( ! empty( $parsed['compatible_api_key'] ) ) {
			$parsed['compatible_api_key'] = $this->decrypt_secret( $parsed['compatible_api_key'] );
		}

		return $parsed;
	}

	public function get( $key, $default = null ) {
		$options = $this->all();

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->all();
		WPAgent_Admin_UI::render_header(
			__( 'Configurações do 37agents', 'wpagent' ),
			__( 'Configure a conexão global com Inteligência Artificial e crie agentes independentes.', 'wpagent' )
		);
		?>
		<div class="wpagents-form-wrap">
			<div class="notice notice-info" style="margin:0 0 16px;padding:12px 16px;border-left-color:#1a73e8;">
				<p><strong><?php esc_html_e( 'Serviços Externos e Privacidade', 'wpagent' ); ?></strong></p>
				<p>
				<?php
				echo wp_kses(
					__( 'Este plugin envia as mensagens digitadas pelos usuários aos serviços de IA configurados (<strong>WordPress AI Connectors</strong>, <strong>Planos de IA &amp; APIs Compatíveis</strong> como Xiaomi MiMo, Alibaba Qwen, Zhipu GLM, Kimi, MiniMax, DeepSeek, e/ou <strong>OpenRouter</strong>) para gerar respostas. Ao instalar e configurar este plugin, você consente com esse envio de dados. Nenhuma mensagem é transmitida antes de uma chave de API ou connector ser configurado pelo administrador.', 'wpagent' ),
					array( 'strong' => array() )
				);
				?>
				</p>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: OpenRouter privacy URL, 2: WordPress AI privacy URL */
							__( 'Políticas de privacidade: <a href="%1$s" target="_blank" rel="noopener noreferrer">OpenRouter</a> | <a href="%2$s" target="_blank" rel="noopener noreferrer">WordPress AI</a>.', 'wpagent' ),
							'https://openrouter.ai/privacy',
							'https://wordpress.org/about/privacy/'
						),
						array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
					);
					?>
				</p>
			</div>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpagent_agent' ) ); ?>"><?php esc_html_e( 'Criar novo agente', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpagent_agent' ) ); ?>"><?php esc_html_e( 'Ver agentes', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-wp-admin' ) ); ?>"><?php esc_html_e( 'Plugin AI do WordPress', 'wpagent' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Connectors', 'wpagent' ); ?></a>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpagent_settings' ); ?>
				<?php $this->render_general_settings( $options ); ?>
				<?php submit_button(); ?>
			</form>
		</div>

		<div class="wpagents-form-wrap" style="margin-top: 24px;">
			<h2><?php esc_html_e( 'Como usar', 'wpagent' ); ?></h2>
			<p><?php esc_html_e( 'Cada agente exibe seu shortcode na tela de edicao e recebe seus arquivos de treinamento na propria configuracao.', 'wpagent' ); ?></p>
			<p><code>[37agents_chat agent="educadoria"]</code> <span class="description">(<?php esc_html_e( 'compatível também com [wpagent_chat]', 'wpagent' ); ?>)</span></p>
		</div>
		<?php
		WPAgent_Admin_UI::render_footer();
	}

	private function render_general_settings( $options ) {
		$embedding_status = get_option( 'wpagent_embedding_status', array() );
		$embedding_status = is_array( $embedding_status ) ? $embedding_status : array();
		$next_embedding_run = wp_next_scheduled( WPAgent_Embeddings::CRON_HOOK );
		$wp_ai_available = function_exists( 'WordPress\\AI\\get_ai_service' ) || function_exists( 'wp_ai_client_prompt' );
		?>
				<h2><?php esc_html_e( 'WordPress AI / Connectors', 'wpagent' ); ?></h2>
				<p>
					<?php esc_html_e( 'O caminho recomendado e configurar OpenAI, Gemini, Claude, OpenRouter ou outros provedores no plugin AI oficial do WordPress em Settings > Connectors. O WPAgent usa essa configuracao para chats e tarefas de IA quando disponivel.', 'wpagent' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Status:', 'wpagent' ); ?></strong>
					<?php if ( $wp_ai_available ) : ?>
						<?php esc_html_e( 'WordPress AI Client detectado.', 'wpagent' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'WordPress AI Client ainda nao foi detectado. Instale/ative o plugin AI oficial ou use o OpenRouter direto abaixo.', 'wpagent' ); ?>
					<?php endif; ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpagent-default-provider-mode"><?php esc_html_e( 'Provedor padrao', 'wpagent' ); ?></label></th>
						<td>
							<select id="wpagent-default-provider-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_provider_mode]">
								<option value="wordpress_ai" <?php selected( $options['default_provider_mode'], 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI / Connectors (recomendado)', 'wpagent' ); ?></option>
								<option value="wpagent_compatible" <?php selected( $options['default_provider_mode'], 'wpagent_compatible' ); ?>><?php esc_html_e( 'Plano de IA / API Compatível (Xiaomi MiMo, GLM, Qwen, Kimi, MiniMax, DeepSeek)', 'wpagent' ); ?></option>
								<option value="wpagent_openrouter" <?php selected( $options['default_provider_mode'], 'wpagent_openrouter' ); ?>><?php esc_html_e( 'OpenRouter direto pelo 37agents', 'wpagent' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Define qual provedor novos agentes herdam por padrão e qual modo assume prioridade quando houver fallback. Agentes existentes podem ser ajustados individualmente na tela do agente.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Modelo padrao do WordPress AI', 'wpagent' ); ?></th>
						<td>
							<p>
								<label for="wpagent-wordpress-ai-provider"><?php esc_html_e( 'Provider', 'wpagent' ); ?></label><br>
								<input id="wpagent-wordpress-ai-provider" class="regular-text" list="wpagent-provider-presets" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wordpress_ai_provider]" value="<?php echo esc_attr( $options['wordpress_ai_provider'] ); ?>" placeholder="openai">
							</p>
							<p>
								<label for="wpagent-wordpress-ai-model"><?php esc_html_e( 'Modelo', 'wpagent' ); ?></label><br>
								<input id="wpagent-wordpress-ai-model" class="regular-text" list="wpagent-model-presets" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wordpress_ai_model]" value="<?php echo esc_attr( $options['wordpress_ai_model'] ); ?>" placeholder="gpt-4.1-mini">
							</p>
							<?php $this->render_ai_model_datalists(); ?>
							<p class="description"><?php esc_html_e( 'Opcional. Quando preenchido, o WPAgent envia este provider/model como preferencia para o WordPress AI Client. Deixe em branco para usar o modelo padrao escolhido pelo conector/plugin AI.', 'wpagent' ); ?></p>
							<p class="description"><?php esc_html_e( 'A listagem automatica de modelos depende de cada conector expor essa informacao. Por enquanto, use o ID oficial do modelo informado pelo fornecedor.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-model"><?php esc_html_e( 'Modelo OpenRouter direto', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-model" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openrouter_model]" value="<?php echo esc_attr( $options['openrouter_model'] ); ?>">
							<p class="description"><?php esc_html_e( 'Valor usado apenas pelo modo OpenRouter direto do WPAgent ou como fallback quando o WordPress AI falhar e houver uma chave OpenRouter salva.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-api-key"><?php esc_html_e( 'OpenRouter API key opcional', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-api-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openrouter_api_key]" value="" placeholder="<?php echo empty( $options['openrouter_api_key'] ) ? '' : esc_attr__( 'Chave salva. Preencha apenas para trocar.', 'wpagent' ); ?>">
							<p class="description"><?php esc_html_e( 'Preencha somente se quiser usar OpenRouter direto pelo WPAgent ou manter um fallback proprio. Se houver um conector OpenRouter no WordPress AI, o WPAgent tambem tenta espelhar esta chave para ele.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-ai-timeout"><?php esc_html_e( 'Timeout das requisições à IA (segundos)', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-ai-timeout" type="number" min="5" max="300" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_request_timeout]" value="<?php echo absint( $options['ai_request_timeout'] ?? 30 ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Tempo máximo de espera por resposta da IA. Aumente se usar modelos mais lentos. Padrão: 30 segundos.', 'wpagent' ); ?></p>
						</td>
					</tr>
				</table>

				<?php
				$compatible_presets = self::get_compatible_provider_presets();
				$compatible_preset  = $options['compatible_provider_preset'] ?? 'xiaomi_token_plan';
				if ( ! isset( $compatible_presets[ $compatible_preset ] ) ) {
					$compatible_preset = 'xiaomi_token_plan';
				}
				$current_key_hint = $compatible_presets[ $compatible_preset ]['key_hint'] ?? '';
				?>
				<h2><?php esc_html_e( 'Planos de IA & APIs Compatíveis (Xiaomi MiMo, GLM, Qwen, Kimi, MiniMax, DeepSeek, OpenCode)', 'wpagent' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Conecte assinaturas de Coding/Token Plan (como Xiaomi MiMo Token Plan, Alibaba Qwen Coding Plan, Zhipu GLM Coding Plan, Moonshot Kimi Code, MiniMax M Plan e Volcengine Ark) ou qualquer API compatível com o protocolo OpenAI / OpenCode.', 'wpagent' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpagent-compatible-preset"><?php esc_html_e( 'Plano / Provedor', 'wpagent' ); ?></label></th>
						<td>
							<select id="wpagent-compatible-preset" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[compatible_provider_preset]" data-presets="<?php echo esc_attr( wp_json_encode( $compatible_presets ) ); ?>">
								<?php foreach ( $compatible_presets as $preset_key => $preset_data ) : ?>
									<option value="<?php echo esc_attr( $preset_key ); ?>" <?php selected( $compatible_preset, $preset_key ); ?>><?php echo esc_html( $preset_data['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description" id="wpagent-compatible-key-hint"><?php echo esc_html( $current_key_hint ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-compatible-base-url"><?php esc_html_e( 'Base URL (Endpoint)', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-compatible-base-url" class="regular-text code" type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[compatible_api_base_url]" value="<?php echo esc_attr( $options['compatible_api_base_url'] ); ?>" placeholder="https://token-plan-cn.xiaomimimo.com/v1">
							<p class="description"><?php esc_html_e( 'Preenchida automaticamente ao selecionar o plano acima. Se o seu painel fornecer uma URL exclusiva, basta colar aqui.', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-compatible-api-key"><?php esc_html_e( 'API Key do Plano / Provedor', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-compatible-api-key" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[compatible_api_key]" value="" placeholder="<?php echo empty( $options['compatible_api_key'] ) ? '' : esc_attr__( 'Chave salva. Preencha apenas para trocar.', 'wpagent' ); ?>">
							<p class="description"><?php esc_html_e( 'Armazenada criptografada no banco de dados (ou via constante WPAGENT_COMPATIBLE_API_KEY no wp-config.php).', 'wpagent' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-compatible-model"><?php esc_html_e( 'Modelo padrão do plano', 'wpagent' ); ?></label></th>
						<td>
							<input id="wpagent-compatible-model" class="regular-text" list="wpagent-compatible-model-presets" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[compatible_model]" value="<?php echo esc_attr( $options['compatible_model'] ); ?>" placeholder="mimo-v2.6-pro">
							<?php self::render_compatible_model_datalist( $compatible_preset ); ?>
							<p class="description"><?php esc_html_e( 'Ex.: mimo-v2.6-pro, qwen3.5-plus, glm-5.3, kimi-k2.7-code, MiniMax-M3, deepseek-chat.', 'wpagent' ); ?></p>
						</td>
					</tr>
				</table>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Padrao: visitantes anonimos', 'wpagent' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allow_guest_chat]" value="1" <?php checked( $options['allow_guest_chat'], '1' ); ?>> <?php esc_html_e( 'Permitir chat sem login para novos agentes.', 'wpagent' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-system-prompt"><?php esc_html_e( 'Instrucao base padrao', 'wpagent' ); ?></label></th>
						<td><textarea id="wpagent-system-prompt" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[system_prompt]"><?php echo esc_textarea( $options['system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limites padrao', 'wpagent' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Treinamentos', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_knowledge_items]" value="<?php echo esc_attr( $options['max_knowledge_items'] ); ?>"></label>
							<label><?php esc_html_e( 'Memorias', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_memory_items]" value="<?php echo esc_attr( $options['max_memory_items'] ); ?>"></label>
							<label><?php esc_html_e( 'Interacoes recentes', 'wpagent' ); ?> <input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_recent_interactions]" value="<?php echo esc_attr( $options['max_recent_interactions'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Embeddings', 'wpagent' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_enabled]" value="1" <?php checked( $options['embedding_enabled'], '1' ); ?>> <?php esc_html_e( 'Ativar busca semantica por embeddings para documentos indexados.', 'wpagent' ); ?></label>
							<p>
								<label><?php esc_html_e( 'Provider', 'wpagent' ); ?> <input class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_provider]" value="<?php echo esc_attr( $options['embedding_provider'] ); ?>"></label>
							</p>
							<p>
								<label><?php esc_html_e( 'Modelo', 'wpagent' ); ?> <input class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_model]" value="<?php echo esc_attr( $options['embedding_model'] ); ?>"></label>
							</p>
							<p>
								<label><?php esc_html_e( 'Lote por cron', 'wpagent' ); ?> <input class="small-text" type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embedding_batch_size]" value="<?php echo esc_attr( $options['embedding_batch_size'] ); ?>"></label>
							</p>
							<p class="description"><?php esc_html_e( 'Nesta primeira versao, o provider suportado e openrouter via /api/v1/embeddings. Os embeddings sao gerados em segundo plano pelo WP-Cron.', 'wpagent' ); ?></p>
							<?php if ( ! empty( $embedding_status ) ) : ?>
								<p>
									<strong><?php esc_html_e( 'Ultimo processamento:', 'wpagent' ); ?></strong>
									<?php echo esc_html( $embedding_status['message'] ?? '' ); ?>
									<?php if ( ! empty( $embedding_status['updated_at'] ) ) : ?>
										<span class="description"><?php echo esc_html( $embedding_status['updated_at'] ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<?php if ( $next_embedding_run ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: next cron date. */
										esc_html__( 'Proximo lote automatico: %s.', 'wpagent' ),
										esc_html( wp_date( 'Y-m-d H:i:s', $next_embedding_run ) )
									);
									?>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Nao ha lote automatico agendado no momento. Salve as configuracoes com embeddings ativos para agendar.', 'wpagent' ); ?></p>
							<?php endif; ?>
							<p>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_process_embeddings_now' ), 'wpagent_process_embeddings_now' ) ); ?>"><?php esc_html_e( 'Processar embeddings agora', 'wpagent' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpagent-enable-global-token-limit"><?php esc_html_e( 'Controle de tokens por usuário', 'wpagent' ); ?></label></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_global_token_limit]" value="1" <?php checked( $options['enable_global_token_limit'], '1' ); ?>> <?php esc_html_e( 'Ativar controle de uso de tokens por usuário', 'wpagent' ); ?></label>
							<p class="description">
								<?php esc_html_e( 'Quando ativado, cada usuário terá um limite mensal de tokens. Administradores não são limitados. O limite pode ser 0 para ilimitado.', 'wpagent' ); ?>
							</p>
							<p>
								<label>
									<?php esc_html_e( 'Limite mensal por usuário', 'wpagent' ); ?>
									<input class="small-text" type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[global_token_limit]" value="<?php echo esc_attr( $options['global_token_limit'] ); ?>">
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Deixe em branco (0) para permitir uso ilimitado. Os tokens são resetados automaticamente no início de cada mês.', 'wpagent' ); ?></p>
						</td>
					</tr>
				</table>
		<?php
	}

	private function render_ai_model_datalists() {
		?>
		<datalist id="wpagent-provider-presets">
			<option value="openai">
			<option value="anthropic">
			<option value="google">
			<option value="openrouter">
		</datalist>
		<datalist id="wpagent-model-presets">
			<option value="gpt-4.1-mini">
			<option value="gpt-4.1">
			<option value="gpt-4o-mini">
			<option value="claude-3-5-sonnet-latest">
			<option value="claude-3-5-haiku-latest">
			<option value="gemini-2.0-flash">
			<option value="gemini-1.5-pro">
		</datalist>
		<?php
	}
}
}

