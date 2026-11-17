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

	private function dispatch_provider_messages( $messages, $agent, $context = 'chat' ) {
		$provider_mode = $agent['provider_mode'] ?? 'wordpress_ai';

		if ( 'wpagent_compatible' === $provider_mode ) {
			try {
				$response = $this->chat_with_compatible_api( $messages, $agent, $context );
			} catch ( Throwable $throwable ) {
				$response = $this->provider_exception_error( 'wpagent_compatible_exception', $throwable );
			}

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			if ( '' === trim( (string) $this->settings->get( 'compatible_api_key', '' ) ) && '' !== trim( (string) $this->settings->get( 'openrouter_api_key', '' ) ) ) {
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

			if ( '' !== trim( (string) $this->settings->get( 'compatible_api_key', '' ) ) ) {
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

		if ( '' === trim( (string) $this->settings->get( 'openrouter_api_key', '' ) ) && '' !== trim( (string) $this->settings->get( 'compatible_api_key', '' ) ) ) {
			try {
				return $this->chat_with_compatible_api( $messages, $agent, $context );
			} catch ( Throwable $throwable ) {
				return $this->provider_exception_error( 'wpagent_compatible_exception', $throwable );
			}
		}

		return $this->safe_chat_with_openrouter( $messages, $agent, $context );
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

	private function chat_with_compatible_api( $messages, $agent, $context = 'chat' ) {
		$api_key = trim( (string) $this->settings->get( 'compatible_api_key', '' ) );
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
		if ( '' === $model ) {
			return new WP_Error( 'wpagent_missing_compatible_model', __( 'Informe o ID do modelo para o Plano de IA / API Compatível nas configurações.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$timeout    = $this->resolve_http_timeout( $context );
		$user_agent = '37agents/' . ( defined( 'WPAGENT_VERSION' ) ? WPAGENT_VERSION : '0.8.0' );

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => $user_agent,
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
			$error_message = '';
			if ( is_array( $body ) ) {
				$error_message = $body['error']['message'] ?? ( $body['message'] ?? '' );
			}
			if ( '' === trim( (string) $error_message ) ) {
				$error_message = wp_remote_retrieve_response_message( $response ) ?: __( 'Erro ao chamar a API do plano de IA configurado.', 'wpagent' );
			}

			return new WP_Error( 'wpagent_compatible_api_error', $error_message, array( 'status' => $code ) );
		}

		$choice = is_array( $body['choices'][0] ?? null ) ? $body['choices'][0] : array();
		$reply  = $this->extract_compatible_reply( $choice );

		if ( '' === trim( (string) $reply ) ) {
			return new WP_Error( 'wpagent_empty_ai_reply', __( 'O fornecedor de IA respondeu sem conteudo.', 'wpagent' ), array( 'status' => 502 ) );
		}

		return array(
			'reply'        => $reply,
			'provider'     => $preset_key ?: 'compatible-api',
			'model'        => $body['model'] ?? $model,
			'token_input'  => absint( $body['usage']['prompt_tokens'] ?? 0 ),
			'token_output' => absint( $body['usage']['completion_tokens'] ?? 0 ),
			'raw'          => is_array( $body ) ? $body : array(),
		);
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
