<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
	}

if ( ! class_exists( 'WPAgent_AI_Client' ) ) {
class WPAgent_AI_Client {
	private $repository;
	private $settings;
	private $agents;
	private $prompt_builder;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents, ?WPAgent_Embeddings $embeddings = null, ?WPAgent_Admin_Abilities $admin_abilities = null, ?WPAgent_Email_Actions $email_actions = null, ?WPAgent_Email_Schedules $email_schedules = null ) {
		$this->repository     = $repository;
		$this->settings       = $settings;
		$this->agents         = $agents;
		$this->prompt_builder = new WPAgent_Prompt_Builder( $repository, $settings, $agents, $embeddings, $admin_abilities, $email_actions, $email_schedules );
	}

	public function chat( $message, $user_id, $agent_slug, $conversation_id = '', $session_id = '', $project_id = null, $context = '' ) {
		$user_id = absint( $user_id );
		$tokens_needed = 100;
		$can_use = $this->repository->can_user_use_tokens( $user_id, $tokens_needed );

		if ( ! $can_use ) {
			return new WP_Error( 'wpagent_token_limit_reached', __( 'Você atingiu o limite mensal de tokens. Tente novamente em 30 dias ou fale com o administrador.', 'wpagent' ), array( 'status' => 429 ) );
		}

		if ( empty( $context ) ) {
			if ( false !== strpos( (string) $conversation_id, 'steps-report' ) || 'steps-report' === $session_id ) {
				$context = 'steps-report';
			} elseif ( false !== strpos( (string) $conversation_id, 'ead-lesson' ) || 'ead-lesson' === $session_id ) {
				$context = 'ead-lesson';
			} elseif ( false !== strpos( (string) $conversation_id, 'ead-syllabus' ) || 'ead-syllabus' === $session_id ) {
				$context = 'ead-syllabus';
			} else {
				$context = 'chat';
			}
		}

		$prompt = $this->prompt_builder->build( $message, $user_id, $agent_slug, $conversation_id, $session_id, $project_id );
		$agent  = $prompt['agent'];

		$response = $this->dispatch_provider_messages( $prompt['messages'], $agent, $context );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->with_prompt_metadata( $response, $prompt );
	}

	public function raw_prompt( $prompt_text, $agent_slug, $system_instruction = '', $context = 'general' ) {
		$agent    = $this->agents->get_agent( $agent_slug );
		$messages = array();
		if ( ! empty( $system_instruction ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_instruction,
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt_text,
		);

		return $this->dispatch_provider_messages( $messages, $agent, $context );
	}

	public function get_effective_compatible_api_key() {
		$compat_key = WPAgent_Settings::clean_api_key( $this->settings->get( 'compatible_api_key', '' ) );
		if ( '' !== $compat_key ) {
			return $compat_key;
		}

		$or_key = WPAgent_Settings::clean_api_key( $this->settings->get( 'openrouter_api_key', '' ) );
		if ( '' !== $or_key && 0 !== strpos( $or_key, 'sk-or-' ) ) {
			return $or_key;
		}

		return '';
	}

	private function dispatch_provider_messages( $messages, $agent, $context = 'chat' ) {
		$provider_mode     = $agent['provider_mode'] ?? 'wordpress_ai';
		$default_mode      = $this->settings->get( 'default_provider_mode', 'wordpress_ai' );
		$direct_compat_key = WPAgent_Settings::clean_api_key( $this->settings->get( 'compatible_api_key', '' ) );
		$compat_key        = $this->get_effective_compatible_api_key();
		$is_xiaomi_key     = '' !== $compat_key && (bool) preg_match( '/^(tp-|ttp-|miMo-)/i', $compat_key );

		if ( 'wpagent_compatible' === $provider_mode || ( 'wpagent_compatible' === $default_mode && '' !== $direct_compat_key ) || $is_xiaomi_key ) {
			try {
				$response = $this->chat_with_compatible_api( $messages, $agent, $context );
			} catch ( Throwable $throwable ) {
				$response = $this->provider_exception_error( 'wpagent_compatible_exception', $throwable );
			}

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			if ( '' === $compat_key && '' !== trim( (string) $this->settings->get( 'openrouter_api_key', '' ) ) ) {
				return $this->safe_chat_with_openrouter( $messages, $agent, $context );
			}

			return $response;
		}

		if ( 'wordpress_ai' === $provider_mode ) {
			try {
				$core_response = $this->chat_with_wordpress_ai( $messages, $agent );
			} catch ( Throwable $throwable ) {
				$core_response = $this->provider_exception_error( 'wpagent_wordpress_ai_exception', $throwable );
			}

			if ( ! is_wp_error( $core_response ) ) {
				return $core_response;
			}

			if ( '' !== $compat_key ) {
				try {
					return $this->chat_with_compatible_api( $messages, $agent, $context );
				} catch ( Throwable $throwable ) {
					return $this->provider_exception_error( 'wpagent_compatible_exception', $throwable );
				}
			}

			if ( '' === trim( (string) $this->settings->get( 'openrouter_api_key', '' ) ) ) {
				return $core_response;
			}
		}

		$openrouter_response = $this->safe_chat_with_openrouter( $messages, $agent, $context );
		if ( ! is_wp_error( $openrouter_response ) ) {
			return $openrouter_response;
		}

		if ( '' !== $compat_key ) {
			try {
				$compat_response = $this->chat_with_compatible_api( $messages, $agent, $context );
				if ( ! is_wp_error( $compat_response ) ) {
					return $compat_response;
				}
				if ( '' === trim( (string) $this->settings->get( 'openrouter_api_key', '' ) ) || 'wpagent_compatible' === $default_mode ) {
					return $compat_response;
				}
			} catch ( Throwable $throwable ) {
				// Ignore and fall back to primary error below.
			}
		}

		return $openrouter_response;
	}

	private function safe_chat_with_openrouter( $messages, $agent, $context = 'chat' ) {
		try {
			return $this->chat_with_openrouter( $messages, $agent, $context );
		} catch ( Throwable $throwable ) {
			return $this->provider_exception_error( 'wpagent_openrouter_exception', $throwable );
		}
	}

	private function chat_with_wordpress_ai( $messages, $agent ) {
		$system_instruction = $this->system_instruction_from_messages( $messages );
		$text               = $this->conversation_text_from_messages( $messages );

		if ( function_exists( 'WordPress\\AI\\get_ai_service' ) ) {
			$service = call_user_func( 'WordPress\\AI\\get_ai_service' );
			$builder = $service->create_textgen_prompt(
				$text,
				array(
					'system_instruction' => $system_instruction,
					'temperature'        => 0.4,
				)
			);
		} elseif ( function_exists( 'wp_ai_client_prompt' ) ) {
			$builder = wp_ai_client_prompt( $text )
				->using_system_instruction( $system_instruction )
				->using_temperature( 0.4 );
		} else {
			return new WP_Error( 'wpagent_wordpress_ai_unavailable', __( 'O WordPress AI Client nao esta disponivel.', 'wpagent' ), array( 'status' => 500 ) );
		}

		if ( ! empty( $agent['wordpress_ai_provider'] ) && ! empty( $agent['wordpress_ai_model'] ) && method_exists( $builder, 'using_model_preference' ) ) {
			$builder = $builder->using_model_preference(
				array(
					$agent['wordpress_ai_provider'],
					$agent['wordpress_ai_model'],
				)
			);
		}

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			return $this->normalize_provider_error( $result );
		}

		$response = array(
			'reply'        => (string) $result,
			'provider'     => $agent['wordpress_ai_provider'] ?: 'wordpress-ai',
			'model'        => $agent['wordpress_ai_model'] ?: '',
			'token_input'  => 0,
			'token_output' => 0,
			'raw'          => array(),
		);

		return $response;
	}

	private function chat_with_openrouter( $messages, $agent, $context = 'chat' ) {
		$api_key = $this->settings->get( 'openrouter_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpagent_missing_api_key', __( 'Configure a chave da OpenRouter nas opcoes do WPAgent.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$model   = $agent['openrouter_model'] ?: $this->settings->get( 'openrouter_model', 'openai/gpt-4.1-mini' );
		$timeout = $this->resolve_http_timeout( $context );

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => $messages,
						'temperature' => 0.4,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->normalize_provider_error( $response );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpagent_openrouter_error', $body['error']['message'] ?? __( 'Erro ao chamar a OpenRouter.', 'wpagent' ), array( 'status' => $code ) );
		}

		$reply = $body['choices'][0]['message']['content'] ?? '';

		if ( '' === trim( (string) $reply ) ) {
			return new WP_Error( 'wpagent_empty_ai_reply', __( 'O fornecedor de IA respondeu sem conteudo.', 'wpagent' ), array( 'status' => 502 ) );
		}

		$response = array(
			'reply'        => $reply,
			'provider'     => 'openrouter',
			'model'        => $body['model'] ?? $model,
			'token_input'  => absint( $body['usage']['prompt_tokens'] ?? 0 ),
			'token_output' => absint( $body['usage']['completion_tokens'] ?? 0 ),
			'raw'          => $body,
		);

		return $response;
	}

	public function test_compatible_connection( $override = array() ) {
		$api_key = WPAgent_Settings::clean_api_key( $override['api_key'] ?? '' );
		if ( '' === $api_key ) {
			$api_key = $this->get_effective_compatible_api_key();
		}
		if ( '' === $api_key ) {
			return new WP_Error( 'wpagent_missing_compatible_api_key', __( 'Informe ou salve uma API Key antes de testar a conexão.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$presets    = class_exists( 'WPAgent_Settings' ) ? WPAgent_Settings::get_compatible_provider_presets() : array();
		$preset_key = sanitize_key( (string) ( $override['preset'] ?? '' ) );
		if ( '' === $preset_key || ! isset( $presets[ $preset_key ] ) ) {
			$preset_key = sanitize_key( (string) $this->settings->get( 'compatible_provider_preset', 'xiaomi_token_plan' ) );
		}
		$preset_defaults = $presets[ $preset_key ] ?? array();

		$base_url = trim( (string) ( $override['base_url'] ?? '' ) );
		if ( '' === $base_url ) {
			$base_url = trim( (string) $this->settings->get( 'compatible_api_base_url', $preset_defaults['base_url'] ?? '' ) );
		}
		if ( '' === $base_url && ! empty( $preset_defaults['base_url'] ) ) {
			$base_url = $preset_defaults['base_url'];
		}

		$model = trim( (string) ( $override['model'] ?? '' ) );
		if ( '' === $model ) {
			$model = trim( (string) $this->settings->get( 'compatible_model', $preset_defaults['default_model'] ?? '' ) );
		}
		if ( '' === $model && ! empty( $preset_defaults['default_model'] ) ) {
			$model = $preset_defaults['default_model'];
		}

		if ( '' === $base_url && preg_match( '/^(tp-|ttp-)/i', $api_key ) ) {
			$base_url = 'https://token-plan-cn.xiaomimimo.com/v1';
		} elseif ( '' === $base_url && preg_match( '/^miMo-/i', $api_key ) ) {
			$base_url = 'https://api.xiaomimimo.com/v1';
		}

		$endpoint = $this->resolve_chat_completions_url( $base_url );
		if ( '' === $endpoint || ! wp_http_validate_url( $endpoint ) ) {
			return new WP_Error( 'wpagent_invalid_compatible_url', __( 'Base URL inválida para o Plano de IA / API Compatível.', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( '' === $model ) {
			if ( $this->is_xiaomi_target( $endpoint, $api_key, $preset_key ) ) {
				$model = 'mimo-v2.6-pro';
			} else {
				return new WP_Error( 'wpagent_missing_compatible_model', __( 'Informe o ID do modelo para testar a conexão.', 'wpagent' ), array( 'status' => 400 ) );
			}
		}

		$test_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Responda apenas OK.',
			),
		);

		$result = $this->execute_compatible_api_request( $test_messages, $api_key, $endpoint, $model, $preset_key, 'test', false, 128 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'message'  => sprintf(
				/* translators: 1: base URL, 2: model id */
				__( 'Conectado com sucesso! Endpoint: %1$s | Modelo: %2$s', 'wpagent' ),
				$result['base_url'],
				$result['model']
			),
			'base_url' => $result['base_url'],
			'model'    => $result['model'],
		);
	}

	private function chat_with_compatible_api( $messages, $agent, $context = 'chat' ) {
		$api_key = $this->get_effective_compatible_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'wpagent_missing_compatible_api_key', __( 'Configure a API Key do Plano de IA / API Compatível nas configurações do 37agents.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$presets    = class_exists( 'WPAgent_Settings' ) ? WPAgent_Settings::get_compatible_provider_presets() : array();
		$preset_key = sanitize_key( (string) ( $agent['compatible_provider_preset'] ?? '' ) );
		if ( '' === $preset_key || ! isset( $presets[ $preset_key ] ) ) {
			$preset_key = sanitize_key( (string) $this->settings->get( 'compatible_provider_preset', 'xiaomi_token_plan' ) );
		}
		$preset_defaults = $presets[ $preset_key ] ?? array();

		$base_url = trim( (string) ( $agent['compatible_api_base_url'] ?? '' ) );
		if ( '' === $base_url ) {
			$base_url = trim( (string) $this->settings->get( 'compatible_api_base_url', $preset_defaults['base_url'] ?? '' ) );
		}
		if ( '' === $base_url && ! empty( $preset_defaults['base_url'] ) ) {
			$base_url = $preset_defaults['base_url'];
		}
		if ( preg_match( '/^(tp-|ttp-|miMo-)/i', $api_key ) && false === stripos( $base_url, 'xiaomimimo.com' ) ) {
			$base_url = 0 === strpos( strtolower( $api_key ), 'mimo-' ) ? 'https://api.xiaomimimo.com/v1' : 'https://token-plan-cn.xiaomimimo.com/v1';
		}

		$endpoint = $this->resolve_chat_completions_url( $base_url );
		if ( '' === $endpoint || ! wp_http_validate_url( $endpoint ) ) {
			return new WP_Error( 'wpagent_invalid_compatible_url', __( 'Base URL inválida para o Plano de IA / API Compatível configurada.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$model = trim( (string) ( $agent['compatible_model'] ?? '' ) );
		if ( '' === $model ) {
			$model = trim( (string) $this->settings->get( 'compatible_model', $preset_defaults['default_model'] ?? '' ) );
		}
		if ( '' === $model && ! empty( $preset_defaults['default_model'] ) ) {
			$model = $preset_defaults['default_model'];
		}
		if ( '' === $model && $this->is_xiaomi_target( $endpoint, $api_key, $preset_key ) ) {
			$model = 'mimo-v2.6-pro';
		}
		if ( '' === $model ) {
			return new WP_Error( 'wpagent_missing_compatible_model', __( 'Informe o ID do modelo para o Plano de IA / API Compatível nas configurações.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$has_agent_base_override = ! empty( $agent['post_id'] ) && '' !== trim( (string) get_post_meta( absint( $agent['post_id'] ), '_wpagent_compatible_api_base_url', true ) );
		$result                  = $this->execute_compatible_api_request( $messages, $api_key, $endpoint, $model, $preset_key, $context, ! $has_agent_base_override );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'reply'        => $result['reply'],
			'provider'     => $result['provider'],
			'model'        => $result['model'],
			'token_input'  => $result['token_input'],
			'token_output' => $result['token_output'],
			'raw'          => $result['raw'],
		);
	}

	private function execute_compatible_api_request( $messages, $api_key, $endpoint, $model, $preset_key, $context, $allow_global_update = true, $max_tokens = null ) {
		$is_xiaomi  = $this->is_xiaomi_target( $endpoint, $api_key, $preset_key );
		$candidates = $this->build_compatible_candidate_endpoints( $endpoint, $api_key, $preset_key );
		$timeout    = 'test' === $context ? 15 : $this->resolve_http_timeout( $context );

		$tested_hosts = array();
		$last_code    = 0;
		$last_message = '';
		$last_wp_err  = null;

		foreach ( $candidates as $candidate_endpoint ) {
			$host = (string) wp_parse_url( $candidate_endpoint, PHP_URL_HOST );
			if ( $host && ! in_array( $host, $tested_hosts, true ) ) {
				$tested_hosts[] = $host;
			}

			$attempt = $this->send_compatible_completion( $candidate_endpoint, $api_key, $model, $messages, $timeout, $is_xiaomi, $max_tokens );
			if ( ! empty( $attempt['ok'] ) ) {
				return $this->finalize_compatible_success( $attempt, $candidate_endpoint, $endpoint, $api_key, $preset_key, $model, $is_xiaomi, $allow_global_update );
			}

			$last_code    = absint( $attempt['code'] ?? 0 );
			$last_message = (string) ( $attempt['message'] ?? '' );
			$last_wp_err  = $attempt['wp_error'] ?? null;

			if ( $is_xiaomi && $this->is_compatible_model_error( $last_code, $last_message ) && ! $this->is_compatible_auth_error( $last_code, $last_message ) ) {
				foreach ( array( 'mimo-v2.6-pro', 'mimo-v2.6-flash', 'mimo-v2-flash' ) as $fallback_model ) {
					if ( $fallback_model === $model ) {
						continue;
					}
					$model_attempt = $this->send_compatible_completion( $candidate_endpoint, $api_key, $fallback_model, $messages, $timeout, true, $max_tokens );
					if ( ! empty( $model_attempt['ok'] ) ) {
						return $this->finalize_compatible_success( $model_attempt, $candidate_endpoint, $endpoint, $api_key, $preset_key, $fallback_model, true, $allow_global_update );
					}
				}
			}

			if ( ! $is_xiaomi ) {
				break;
			}

			// Stop probing other Xiaomi clusters if a non-auth HTTP error occurred and was not a transport timeout/DNS error.
			if ( ! $last_wp_err && ! $this->is_compatible_auth_error( $last_code, $last_message ) && 404 !== $last_code ) {
				break;
			}
		}

		if ( $last_wp_err instanceof WP_Error && ! $is_xiaomi ) {
			return $last_wp_err;
		}

		if ( $is_xiaomi && ! empty( $tested_hosts ) ) {
			$error_detail = '' !== $last_message ? $last_message : __( 'Falha ao autenticar', 'wpagent' );
			return new WP_Error(
				'wpagent_compatible_api_error',
				sprintf(
					/* translators: 1: provider error message, 2: tested hostnames */
					__( 'Erro Xiaomi MiMo (%1$s). Clusters verificados: %2$s. Confira em platform.xiaomimimo.com se a chave (tp-... para Token Plan ou sk-... para API padrão) está ativa e com cota disponível.', 'wpagent' ),
					$error_detail,
					implode( ', ', $tested_hosts )
				),
				array( 'status' => $last_code ?: 401 )
			);
		}

		return new WP_Error(
			'wpagent_compatible_api_error',
			$last_message ?: __( 'Erro ao chamar a API do plano de IA configurado.', 'wpagent' ),
			array( 'status' => $last_code ?: 502 )
		);
	}

	private function send_compatible_completion( $endpoint, $api_key, $model, $messages, $timeout, $is_xiaomi, $max_tokens = null ) {
		$user_agent = '37agents/' . ( defined( 'WPAGENT_VERSION' ) ? WPAGENT_VERSION : '0.8.0' );
		$headers    = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'User-Agent'    => $user_agent,
		);
		if ( $is_xiaomi ) {
			$headers['api-key'] = $api_key;
		}

		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => 0.4,
		);
		if ( null !== $max_tokens && absint( $max_tokens ) > 0 ) {
			$payload['max_tokens'] = absint( $max_tokens );
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$normalized = $this->normalize_provider_error( $response );
			return array(
				'ok'       => false,
				'code'     => 502,
				'message'  => $normalized->get_error_message(),
				'wp_error' => $normalized,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = '';
			if ( is_array( $body ) ) {
				$error_message = $body['error']['message'] ?? ( $body['message'] ?? '' );
			}
			if ( '' === trim( (string) $error_message ) ) {
				$error_message = wp_remote_retrieve_response_message( $response ) ?: __( 'Erro ao chamar a API do plano de IA configurado.', 'wpagent' );
			}

			return array(
				'ok'       => false,
				'code'     => $code,
				'message'  => trim( (string) $error_message ),
				'wp_error' => null,
			);
		}

		$choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : array();
		$reply  = $this->extract_compatible_reply( $choice );

		if ( '' === trim( (string) $reply ) ) {
			return array(
				'ok'       => false,
				'code'     => 502,
				'message'  => __( 'O fornecedor de IA respondeu sem conteudo.', 'wpagent' ),
				'wp_error' => null,
			);
		}

		return array(
			'ok'    => true,
			'code'  => $code,
			'body'  => is_array( $body ) ? $body : array(),
			'reply' => $reply,
		);
	}

	private function finalize_compatible_success( $attempt, $working_endpoint, $initial_endpoint, $api_key, $preset_key, $requested_model, $is_xiaomi, $allow_global_update ) {
		$body          = is_array( $attempt['body'] ?? null ) ? $attempt['body'] : array();
		$resolved_base = (string) preg_replace( '#/chat/completions$#i', '', $working_endpoint );

		if ( $is_xiaomi ) {
			set_transient( 'wpagent_xiaomi_ep_' . md5( $api_key ), $working_endpoint, 7 * DAY_IN_SECONDS );
			if ( $allow_global_update && $working_endpoint !== $initial_endpoint ) {
				$raw_options = get_option( WPAgent_Settings::OPTION_NAME, array() );
				$raw_options = is_array( $raw_options ) ? $raw_options : array();
				if ( ( $raw_options['compatible_api_base_url'] ?? '' ) !== $resolved_base ) {
					$raw_options['compatible_api_base_url'] = esc_url_raw( $resolved_base );
					update_option( WPAgent_Settings::OPTION_NAME, $raw_options );
				}
			}
		}

		return array(
			'reply'        => (string) ( $attempt['reply'] ?? '' ),
			'provider'     => $preset_key ?: 'compatible-api',
			'model'        => $body['model'] ?? $requested_model,
			'base_url'     => $resolved_base,
			'token_input'  => absint( $body['usage']['prompt_tokens'] ?? 0 ),
			'token_output' => absint( $body['usage']['completion_tokens'] ?? 0 ),
			'raw'          => $body,
		);
	}

	private function is_xiaomi_target( $endpoint, $api_key, $preset_key ) {
		return false !== stripos( (string) $endpoint, 'xiaomimimo.com' )
			|| 0 === strpos( (string) $preset_key, 'xiaomi_' )
			|| (bool) preg_match( '/^(tp-|ttp-|miMo-)/i', (string) $api_key );
	}

	private function build_compatible_candidate_endpoints( $endpoint, $api_key, $preset_key ) {
		if ( ! $this->is_xiaomi_target( $endpoint, $api_key, $preset_key ) ) {
			return array( $endpoint );
		}

		$candidates = array();
		$cached     = get_transient( 'wpagent_xiaomi_ep_' . md5( $api_key ) );
		if ( is_string( $cached ) && '' !== $cached && wp_http_validate_url( $cached ) ) {
			$candidates[] = $cached;
		}

		$is_token_plan_key = (bool) preg_match( '/^(tp-|ttp-)/i', $api_key );
		if ( $is_token_plan_key ) {
			if ( false !== stripos( $endpoint, 'token-plan-' ) ) {
				$candidates[] = $endpoint;
			}
			$candidates[] = 'https://token-plan-sgp.xiaomimimo.com/v1/chat/completions';
			$candidates[] = 'https://token-plan-cn.xiaomimimo.com/v1/chat/completions';
			$candidates[] = 'https://token-plan-ams.xiaomimimo.com/v1/chat/completions';
			$candidates[] = $endpoint;
			$candidates[] = 'https://api.xiaomimimo.com/v1/chat/completions';
		} else {
			if ( false === stripos( $endpoint, 'token-plan-' ) && false !== stripos( $endpoint, 'xiaomimimo.com' ) ) {
				$candidates[] = $endpoint;
			}
			$candidates[] = 'https://api.xiaomimimo.com/v1/chat/completions';
			$candidates[] = $endpoint;
			$candidates[] = 'https://token-plan-sgp.xiaomimimo.com/v1/chat/completions';
			$candidates[] = 'https://token-plan-cn.xiaomimimo.com/v1/chat/completions';
			$candidates[] = 'https://token-plan-ams.xiaomimimo.com/v1/chat/completions';
		}

		$unique = array();
		foreach ( $candidates as $url ) {
			$url = trim( (string) $url );
			if ( '' !== $url && wp_http_validate_url( $url ) && ! in_array( $url, $unique, true ) ) {
				$unique[] = $url;
			}
		}

		return $unique;
	}

	private function is_compatible_auth_error( $code, $message ) {
		if ( in_array( absint( $code ), array( 401, 403 ), true ) ) {
			return true;
		}
		$msg = strtolower( (string) $message );
		return false !== strpos( $msg, 'invalid api key' )
			|| false !== strpos( $msg, 'invalid_api_key' )
			|| false !== strpos( $msg, 'incorrect api key' )
			|| false !== strpos( $msg, 'unauthorized' )
			|| false !== strpos( $msg, 'authentication' )
			|| false !== strpos( $msg, 'invalid token' );
	}

	private function is_compatible_model_error( $code, $message ) {
		if ( ! in_array( absint( $code ), array( 400, 404 ), true ) ) {
			return false;
		}
		$msg = strtolower( (string) $message );
		return false !== strpos( $msg, 'model' ) || false !== strpos( $msg, 'not found' ) || false !== strpos( $msg, 'unsupported' );
	}

	private function resolve_http_timeout( $context ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		// Content generation contexts (lessons, syllabus, reports) require generous timeouts.
		$is_heavy_task      = in_array( $context, array( 'ead-lesson', 'ead-syllabus', 'steps-report', 'bulk-summary' ), true );
		$configured_timeout = absint( $this->settings->get( 'ai_request_timeout', 30 ) );
		$base_timeout       = $is_heavy_task ? max( 120, $configured_timeout ) : max( 60, $configured_timeout );
		$timeout            = (int) apply_filters( 'wpagent_ai_request_timeout', $base_timeout, $context );

		return max( 30, min( 300, $timeout ) );
	}

	private function resolve_chat_completions_url( $base_url ) {
		$base_url = trim( (string) $base_url );
		if ( '' === $base_url ) {
			return '';
		}

		$normalized = untrailingslashit( $base_url );
		$normalized = (string) preg_replace( '#/anthropic(/v1)?$#i', '/v1', $normalized );
		$normalized = (string) preg_replace( '#/v1/messages$#i', '/v1/chat/completions', $normalized );
		$normalized = (string) preg_replace( '#/messages$#i', '/chat/completions', $normalized );

		if ( ! preg_match( '#/chat/completions$#i', $normalized ) ) {
			$normalized .= '/chat/completions';
		}

		return $normalized;
	}

	private function extract_compatible_reply( $choice ) {
		$message = is_array( $choice['message'] ?? null ) ? $choice['message'] : array();
		$content = $message['content'] ?? '';

		if ( is_array( $content ) ) {
			$parts = array();
			foreach ( $content as $part ) {
				if ( is_string( $part ) ) {
					$parts[] = $part;
				} elseif ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
					$parts[] = $part['text'];
				}
			}
			$content = implode( "\n", $parts );
		}

		$reply = trim( (string) $content );
		if ( '' !== $reply ) {
			$stripped = preg_replace( '/^\s*<think>.*?<\/think>\s*/is', '', $reply );
			if ( is_string( $stripped ) && '' !== trim( $stripped ) ) {
				$reply = trim( $stripped );
			}
			return $reply;
		}

		return trim( (string) ( $message['reasoning_content'] ?? '' ) );
	}

	public function maybe_extract_memory( $message, $reply, $interaction_id, $user_id, $agent_slug ) {
		if ( empty( $user_id ) ) {
			return;
		}

		$text = strtolower( wp_strip_all_tags( $message ) );
		$markers = array( 'meu ', 'minha ', 'gosto ', 'prefiro ', 'sou ', 'estou ', 'preciso ', 'quero ', 'tenho ' );

		foreach ( $markers as $marker ) {
			if ( false !== strpos( $text, $marker ) ) {
				$content = 'O usuario disse: ' . $this->compact_memory_sentence( $message );
				$this->repository->add_memory( $user_id, $agent_slug, $content, 'profile', $interaction_id, 3 );
				return;
			}
		}
	}

	private function system_instruction_from_messages( $messages ) {
		$lines = array();

		foreach ( $messages as $message ) {
			if ( 'system' === ( $message['role'] ?? '' ) ) {
				$lines[] = $message['content'] ?? '';
			}
		}

		return implode( "\n\n", array_filter( $lines ) );
	}

	private function conversation_text_from_messages( $messages ) {
		$lines = array();

		foreach ( $messages as $message ) {
			$role    = strtoupper( $message['role'] ?? 'user' );
			$content = $message['content'] ?? '';
			if ( 'SYSTEM' === $role ) {
				continue;
			}
			$lines[] = $role . ":\n" . $content;
		}

		return implode( "\n\n", $lines );
	}

	private function compact_memory_sentence( $message ) {
		$message = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );

		if ( strlen( $message ) > 260 ) {
			return substr( $message, 0, 257 ) . '...';
		}

		return $message;
	}

	private function request_timeout( $context ) {
		$timeout = (int) apply_filters( 'wpagent_ai_request_timeout', 90, $context );

		return max( 15, min( 180, $timeout ) );
	}

	private function provider_exception_error( $code, Throwable $throwable ) {
		$message = $throwable->getMessage();

		if ( $this->is_timeout_message( $message ) ) {
			return $this->timeout_error( $code, $message );
		}

		return new WP_Error(
			$code,
			__( 'Nao foi possivel concluir a resposta do provedor de IA. Tente novamente em instantes.', 'wpagent' ),
			array(
				'status'         => 502,
				'provider_error' => $code,
			)
		);
	}

	private function normalize_provider_error( WP_Error $error ) {
		$message = $error->get_error_message();

		if ( $this->is_timeout_message( $message ) ) {
			return $this->timeout_error( $error->get_error_code(), $message );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $message ) {
			error_log( 'WPAgent AI provider error (' . $error->get_error_code() . '): ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$data = $error->get_error_data();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 502;

		return new WP_Error(
			$error->get_error_code(),
			$message ?: __( 'Nao foi possivel concluir a resposta do provedor de IA. Tente novamente em instantes.', 'wpagent' ),
			array( 'status' => $status )
		);
	}

	private function timeout_error( $code, $original_message = '' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $original_message ) {
			error_log( 'WPAgent AI timeout: ' . $original_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_Error(
			'wpagent_ai_timeout',
			__( 'O provedor de IA demorou demais para responder. Tente novamente em instantes.', 'wpagent' ),
			array(
				'status'         => 504,
				'provider_error' => $code,
			)
		);
	}

	private function is_timeout_message( $message ) {
		$message = strtolower( (string) $message );

		return false !== strpos( $message, 'timed out' )
			|| false !== strpos( $message, 'operation timed out' )
			|| false !== strpos( $message, 'curl error 28' )
			|| false !== strpos( $message, 'timeout' );
	}

	private function with_prompt_metadata( $response, $prompt ) {
		$response['knowledge'] = $prompt['knowledge'] ?? array();
		$response['memories']  = $prompt['memories'] ?? array();
		$response['context']   = array(
			'knowledge_count' => count( $response['knowledge'] ),
			'memory_count'    => count( $response['memories'] ),
		);

		return $response;
	}
}
}
