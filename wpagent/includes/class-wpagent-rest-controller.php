<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_REST_Controller' ) ) {
class WPAgent_REST_Controller {
	private $repository;
	private $settings;
	private $agents;
	private $ai_client;
	private $admin_abilities;
	private $email_actions;
	private $email_schedules;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents, WPAgent_AI_Client $ai_client, ?WPAgent_Admin_Abilities $admin_abilities = null, ?WPAgent_Email_Actions $email_actions = null, ?WPAgent_Email_Schedules $email_schedules = null ) {
		$this->repository      = $repository;
		$this->settings        = $settings;
		$this->agents          = $agents;
		$this->ai_client       = $ai_client;
		$this->admin_abilities = $admin_abilities;
		$this->email_actions   = $email_actions;
		$this->email_schedules = $email_schedules;
	}

	public function register_routes() {
		register_rest_route(
			'wpagent/v1',
			'/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_conversations' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/projects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_projects' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_project' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/projects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_project' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_project' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/conversations/(?P<conversation_id>[a-zA-Z0-9\\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation_messages' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rename_conversation' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => array( $this, 'can_manage_conversations' ),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/conversations/(?P<conversation_id>[a-zA-Z0-9\\-]+)/delete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'delete_conversation' ),
				'permission_callback' => array( $this, 'can_manage_conversations' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => array( $this, 'can_chat' ),
				'args'                => array(
					'message'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'agent_slug' => array(
						'type'     => 'string',
						'required' => false,
					),
					'session_id' => array(
						'type'     => 'string',
						'required' => false,
					),
					'conversation_id' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/chat/stream',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_stream' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'capture_lead' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/chat/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_chat_file' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user_profile' ),
					'permission_callback' => array( $this, 'can_manage_user_profile' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_user_profile' ),
					'permission_callback' => array( $this, 'can_manage_user_profile' ),
					'args'                => array(
						'content' => array(
							'type'     => 'string',
							'required' => false,
						),
						'structured' => array(
							'type'     => 'object',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/abilities/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_ability' ),
				'permission_callback' => array( $this, 'can_run_admin_ability' ),
				'args'                => array(
					'agent_slug' => array(
						'type'     => 'string',
						'required' => true,
					),
					'ability'    => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/email/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_email' ),
				'permission_callback' => array( $this, 'can_send_email' ),
				'args'                => array(
					'agent_slug' => array(
						'type'     => 'string',
						'required' => true,
					),
					'proposal'   => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/email/schedule',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'schedule_email' ),
				'permission_callback' => array( $this, 'can_schedule_email' ),
				'args'                => array(
					'agent_slug' => array(
						'type'     => 'string',
						'required' => true,
					),
					'proposal'   => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/progress',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_progress' ),
					'permission_callback' => array( $this, 'can_chat' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_progress' ),
					'permission_callback' => array( $this, 'can_chat' ),
				),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/progress/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_progress' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/steps/generate-report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_steps_report' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/ead/generate-syllabus',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_ead_syllabus' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'wpagent/v1',
			'/ead/generate-lesson',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_ead_lesson' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);
	}

	public function can_manage_conversations( ?WP_REST_Request $request = null ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( $request ) {
			$agent_slug = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
			$agent      = $this->agents->get_agent( $agent_slug );
			if ( ! $this->user_can_access_agent( $agent ) ) {
				return false;
			}
			if ( '1' === ( $agent['admin_assistant'] ?? '0' ) && ! current_user_can( 'manage_options' ) ) {
				return false;
			}
		}

		return true;
	}

	public function can_chat( WP_REST_Request $request ) {
		$agent_slug = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$agent      = $this->agents->get_agent( $agent_slug );

		if ( ! $this->user_can_access_agent( $agent ) ) {
			return false;
		}

		if ( '1' === ( $agent['admin_assistant'] ?? '0' ) ) {
			return current_user_can( 'manage_options' );
		}

		if ( is_user_logged_in() ) {
			return true;
		}

		if ( '1' !== ( $agent['allow_guest_chat'] ?? '0' ) ) {
			return false;
		}

		return $this->check_guest_rate_limit( $agent['slug'] );
	}

	public function can_run_admin_ability() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function can_send_email( WP_REST_Request $request ) {
		if ( ! $this->email_actions ) {
			return false;
		}

		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		if ( ! $this->user_can_access_agent( $agent ) ) {
			return false;
		}
		if ( '1' === ( $agent['admin_assistant'] ?? '0' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			return '1' === ( $agent['email_actions_enabled'] ?? '0' );
		}

		return '1' === ( $agent['email_actions_enabled'] ?? '0' ) && '1' === ( $agent['allow_guest_chat'] ?? '0' );
	}

	public function can_schedule_email( WP_REST_Request $request ) {
		if ( ! $this->email_schedules ) {
			return false;
		}

		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		if ( ! $this->user_can_access_agent( $agent ) ) {
			return false;
		}
		if ( '1' === ( $agent['admin_assistant'] ?? '0' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$enabled = '1' === ( $agent['email_actions_enabled'] ?? '0' ) || '1' === ( $agent['email_schedules_enabled'] ?? '0' );

		if ( is_user_logged_in() ) {
			return $enabled;
		}

		return $enabled && '1' === ( $agent['allow_guest_chat'] ?? '0' );
	}

	public function can_manage_user_profile( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		if ( ! $this->user_can_access_agent( $agent ) ) {
			return false;
		}

		if ( '1' === ( $agent['admin_assistant'] ?? '0' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return '1' === ( $agent['user_profile_enabled'] ?? '0' );
	}

	private function user_can_access_agent( $agent ) {
		$allowed_roles = $agent['allowed_roles'] ?? array();
		// Empty = unrestricted access
		if ( empty( $allowed_roles ) ) {
			return true;
		}
		// Guest check
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}
		return false;
	}

	public function chat( WP_REST_Request $request ) {
		$result = $this->process_chat_request( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public function chat_stream( WP_REST_Request $request ) {
		$result = $this->process_chat_request( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		status_header( 200 );
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			@ob_end_flush();
		}

		$reply  = (string) ( $result['reply'] ?? '' );
		$tokens = preg_split( '/(\s+)/u', $reply, -1, PREG_SPLIT_DELIM_CAPTURE );
		$buffer = '';

		if ( is_array( $tokens ) ) {
			$count = 0;
			foreach ( $tokens as $token ) {
				$buffer .= $token;
				$count++;
				if ( $count >= 6 ) {
					echo 'data: ' . wp_json_encode( array( 'delta' => $buffer ) ) . "\n\n";
					@flush();
					$buffer = '';
					$count  = 0;
				}
			}
		}

		if ( '' !== $buffer ) {
			echo 'data: ' . wp_json_encode( array( 'delta' => $buffer ) ) . "\n\n";
			@flush();
		}

		$done_payload         = $result;
		$done_payload['done'] = true;
		echo 'data: ' . wp_json_encode( $done_payload ) . "\n\n";
		@flush();
		exit;
	}

	public function capture_lead( WP_REST_Request $request ) {
		$agent_slug      = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$agent           = $this->agents->get_agent( $agent_slug );
		$name            = substr( sanitize_text_field( (string) ( $request->get_param( 'name' ) ?? '' ) ), 0, 190 );
		$email           = sanitize_email( (string) ( $request->get_param( 'email' ) ?? '' ) );
		$session_id      = sanitize_text_field( $request->get_param( 'session_id' ) ?: wp_generate_uuid4() );
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) ?: '' );
		$user_id         = get_current_user_id();

		if ( '1' !== ( $agent['lead_capture_enabled'] ?? '0' ) ) {
			return new WP_Error( 'wpagent_lead_disabled', __( 'A captura de leads está desativada para este agente.', 'wpagent' ), array( 'status' => 403 ) );
		}

		if ( '' === trim( $name ) ) {
			return new WP_Error( 'wpagent_invalid_lead_name', __( 'Informe seu nome.', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpagent_invalid_lead_email', __( 'Informe um e-mail válido.', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( $user_id > 0 ) {
			$this->repository->add_memory(
				$user_id,
				$agent['slug'],
				sprintf( 'Lead identificado: %s (%s)', $name, $email ),
				'lead',
				null,
				5
			);
		} else {
			set_transient(
				'wpagent_guest_lead_' . md5( $agent['slug'] . '|' . $session_id ),
				array(
					'name'  => $name,
					'email' => $email,
				),
				7 * DAY_IN_SECONDS
			);
		}

		$this->dispatch_webhook(
			$agent,
			'lead_captured',
			array(
				'name'            => $name,
				'email'           => $email,
				'user_id'         => $user_id,
				'session_id'      => $session_id,
				'conversation_id' => $conversation_id,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'name'    => $name,
				'email'   => $email,
				'message' => __( 'Dados recebidos com sucesso.', 'wpagent' ),
			)
		);
	}

	private function dispatch_webhook( $agent, $event, $payload ) {
		if ( '1' !== ( $agent['webhook_enabled'] ?? '0' ) ) {
			return;
		}

		$url = esc_url_raw( $agent['webhook_url'] ?? '' );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return;
		}

		$body = wp_json_encode(
			array(
				'plugin'    => '37agents',
				'event'     => sanitize_key( $event ),
				'timestamp' => current_time( 'c' ),
				'site_url'  => home_url( '/' ),
				'agent'     => array(
					'slug' => $agent['slug'] ?? 'default',
					'name' => $agent['name'] ?? '',
				),
				'payload'   => $payload,
			)
		);

		wp_safe_remote_post(
			$url,
			array(
				'blocking'    => false,
				'timeout'     => 5,
				'data_format' => 'body',
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => $body,
			)
		);
	}

	private function process_chat_request( WP_REST_Request $request ) {
		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
		$agent      = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$agent_slug = $agent['slug'];
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) ?: wp_generate_uuid4() );
		$user_id    = get_current_user_id();
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) ?: '' );
		$project_id      = $request->get_param( 'project_id' ) ? absint( $request->get_param( 'project_id' ) ) : null;

		if ( empty( trim( $message ) ) ) {
			return new WP_Error( 'wpagent_empty_message', __( 'Envie uma mensagem para o agente.', 'wpagent' ), array( 'status' => 400 ) );
		}

		if ( ! $this->user_can_access_agent( $agent ) ) {
			return new WP_Error( 'wpagent_forbidden', __( 'Você não tem permissão para acessar este agente.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$limit_error = $this->check_agent_token_limits( $agent );
		if ( is_wp_error( $limit_error ) ) {
			return $limit_error;
		}

		if ( ! $conversation_id ) {
			$conversation_id = wp_generate_uuid4();
		}

		$conversation    = $this->repository->get_conversation( $conversation_id, $user_id, $agent_slug );
		$is_first_message = false;

		if ( $conversation && 0 === $user_id ) {
			$existing_interactions = $this->repository->get_conversation_interactions( $conversation_id, $agent_slug, 1 );
			if ( ! empty( $existing_interactions ) && ( $existing_interactions[0]['session_id'] ?? '' ) !== $session_id ) {
				$conversation_id = wp_generate_uuid4();
				$conversation    = null;
			}
		}

		if ( ! $conversation ) {
			$existing_any = $this->repository->get_conversation_by_id( $conversation_id );
			if ( $existing_any ) {
				$conversation_id = wp_generate_uuid4();
			}
			$title = $this->conversation_title_from_message( $message );
			if ( ! $user_id ) {
				$title = sprintf( __( 'Visitante: %s', 'wpagent' ), $title );
			}
			$conversation     = $this->repository->create_conversation( $user_id, $agent_slug, $title, $conversation_id, $user_id ? $project_id : null );
			$is_first_message = true;
		} elseif ( $user_id && $project_id && ( empty( $conversation['project_id'] ) || absint( $conversation['project_id'] ) !== $project_id ) ) {
			$this->repository->update_conversation_project( $conversation_id, $user_id, $agent_slug, $project_id );
			$conversation['project_id'] = $project_id;
		}

		$conversation_id = $conversation['conversation_id'];

		try {
			$response = $this->ai_client->chat( $message, $user_id, $agent_slug, $conversation_id, $session_id, $project_id );
		} catch ( Throwable $throwable ) {
			$response = new WP_Error( 'wpagent_chat_exception', $throwable->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$ability_proposal = null;
		if ( $this->admin_abilities && '1' === ( $agent['admin_assistant'] ?? '0' ) && current_user_can( 'manage_options' ) ) {
			$proposal = $this->admin_abilities->extract_proposal( $response['reply'] );
			if ( $proposal ) {
				$response['reply'] = $proposal['reply'];
				$ability_proposal  = $proposal['proposal'];
			}
		}

		$email_proposal = null;
		if ( $this->email_actions ) {
			$proposal = $this->email_actions->extract_proposal( $response['reply'], $agent );
			if ( $proposal ) {
				$response['reply'] = $proposal['reply'];
				$email_proposal    = $proposal['proposal'];
			}
		}

		$email_schedule_proposal = null;
		$auto_confirmed_schedule = false;
		if ( $this->email_schedules ) {
			$proposal_res = $this->email_schedules->extract_schedule_proposal(
				$response['reply'],
				$agent,
				array(
					'conversation_id' => $conversation_id,
					'session_id'      => $session_id,
				)
			);
			if ( $proposal_res ) {
				$response['reply']       = $proposal_res['reply'];
				$email_schedule_proposal = $proposal_res['proposal'];
				$auto_confirmed_schedule = ! empty( $proposal_res['auto_confirmed'] );
			}
		}

		$interaction_id = $this->repository->record_interaction(
			array(
				'user_id'         => $user_id,
				'agent_slug'      => $agent_slug,
				'conversation_id' => $conversation_id,
				'session_id'      => $session_id,
				'message'         => $message,
				'reply'           => $response['reply'],
				'model'           => $response['model'],
				'provider'        => $response['provider'],
				'token_input'     => $response['token_input'],
				'token_output'    => $response['token_output'],
				'metadata'        => array(
					'ip_hash'                 => $this->client_ip_hash(),
					'knowledge_used'          => ! empty( $response['knowledge'] ),
					'knowledge_count'         => count( $response['knowledge'] ?? array() ),
					'knowledge_sources'       => $this->knowledge_sources_for_metadata( $response['knowledge'] ?? array() ),
					'memory_count'            => count( $response['memories'] ?? array() ),
					'ability_proposed'        => ! empty( $ability_proposal ),
					'ability_name'            => $ability_proposal['ability'] ?? '',
					'email_proposed'          => ! empty( $email_proposal ),
					'email_subject'           => $email_proposal['subject'] ?? '',
					'email_schedule_proposed' => ! empty( $email_schedule_proposal ),
					'email_schedule_name'     => $email_schedule_proposal['name'] ?? '',
				),
			)
		);

		$total_tokens = $response['token_input'] + $response['token_output'];
		$this->repository->track_tokens_usage( $user_id, $total_tokens );

		if ( $conversation_id ) {
			$this->repository->touch_conversation( $conversation_id, $user_id, $agent_slug );
		}

		if ( $is_first_message ) {
			$guest_lead = 0 === $user_id ? get_transient( 'wpagent_guest_lead_' . md5( $agent_slug . '|' . $session_id ) ) : null;
			$this->dispatch_webhook(
				$agent,
				'conversation_started',
				array(
					'conversation_id' => $conversation_id,
					'session_id'      => $session_id,
					'user_id'         => $user_id,
					'first_message'   => $message,
					'lead'            => is_array( $guest_lead ) ? $guest_lead : null,
				)
			);
		}

		$this->ai_client->maybe_extract_memory( $message, $response['reply'], $interaction_id, $user_id, $agent_slug );

		$current_monthly     = $this->repository->get_user_monthly_usage( $user_id );
		$current_usage       = (int) ( $current_monthly['total_tokens'] ?? 0 );
		$global_limit        = (int) $this->settings->get( 'global_token_limit', 100000 );
		$enable_global_limit = '1' === $this->settings->get( 'enable_global_token_limit', '0' );
		$monthly_limit       = $enable_global_limit && ! current_user_can( 'manage_options' ) ? $global_limit : 0;

		return array(
			'reply'             => $response['reply'],
			'session_id'        => $session_id,
			'conversation_id'   => $conversation_id,
			'interaction_id'    => $interaction_id,
			'provider'          => $response['provider'],
			'model'             => $response['model'],
			'knowledge_used'    => ! empty( $response['knowledge'] ),
			'knowledge_sources' => $this->knowledge_sources_for_metadata( $response['knowledge'] ?? array() ),
			'proposed_ability'  => $ability_proposal,
			'proposed_email'    => $email_proposal,
			'proposed_schedule' => $email_schedule_proposal,
			'auto_confirmed'    => $auto_confirmed_schedule,
			'token_usage'       => array(
				'current_monthly'     => $current_usage,
				'monthly_limit'       => $monthly_limit,
				'enable_global_limit' => $enable_global_limit,
				'remaining'           => $monthly_limit > 0 ? max( 0, $monthly_limit - $current_usage ) : null,
			),
		);
	}

	public function send_email( WP_REST_Request $request ) {
		if ( ! $this->email_actions ) {
			return new WP_Error( 'wpagent_email_actions_unavailable', __( 'O envio de email do WPAgent nao esta disponivel.', 'wpagent' ), array( 'status' => 501 ) );
		}

		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$proposal = $request->get_param( 'proposal' );
		$result = $this->email_actions->send(
			is_array( $proposal ) ? $proposal : array(),
			$agent,
			array(
				'conversation_id' => sanitize_text_field( $request->get_param( 'conversation_id' ) ?: '' ),
				'session_id'      => sanitize_text_field( $request->get_param( 'session_id' ) ?: '' ),
				'interaction_id'  => absint( $request->get_param( 'interaction_id' ) ?: 0 ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public function schedule_email( WP_REST_Request $request ) {
		if ( ! $this->email_schedules ) {
			return new WP_Error( 'wpagent_email_schedules_unavailable', __( 'O agendamento de email do WPAgent nao esta disponivel.', 'wpagent' ), array( 'status' => 501 ) );
		}

		$agent    = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$proposal = $request->get_param( 'proposal' );
		$result   = $this->email_schedules->confirm(
			is_array( $proposal ) ? $proposal : array(),
			$agent,
			array(
				'conversation_id' => sanitize_text_field( $request->get_param( 'conversation_id' ) ?: '' ),
				'session_id'      => sanitize_text_field( $request->get_param( 'session_id' ) ?: '' ),
				'interaction_id'  => absint( $request->get_param( 'interaction_id' ) ?: 0 ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public function run_ability( WP_REST_Request $request ) {
		if ( ! $this->admin_abilities ) {
			return new WP_Error( 'wpagent_abilities_unavailable', __( 'A Abilities API do WordPress nao esta disponivel.', 'wpagent' ), array( 'status' => 501 ) );
		}

		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) );
		if ( '1' !== ( $agent['admin_assistant'] ?? '0' ) ) {
			return new WP_Error( 'wpagent_agent_not_admin_assistant', __( 'Este agente nao esta habilitado como assistente interno do admin.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$ability = sanitize_text_field( $request->get_param( 'ability' ) );
		$input   = $request->get_param( 'input' );
		$result  = $this->admin_abilities->execute( $ability, $input );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'executed' => true,
				'ability'  => $result['ability'],
				'result'   => $result['result'],
			)
		);
	}

	public function get_user_profile( WP_REST_Request $request ) {
		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$profile = $this->repository->get_user_profile_data( get_current_user_id(), $agent['slug'] );

		return rest_ensure_response(
			array(
				'enabled'     => '1' === ( $agent['user_profile_enabled'] ?? '0' ),
				'label'       => $agent['user_profile_label'] ?? __( 'Sobre voce', 'wpagent' ),
				'description' => $agent['user_profile_description'] ?? __( 'Compartilhe informacoes que ajudam este agente a personalizar as respostas.', 'wpagent' ),
				'fields'      => $agent['user_profile_fields'] ?? array(),
				'content'     => $profile['free_text'],
				'structured'  => $profile['structured'],
			)
		);
	}

	public function save_user_profile( WP_REST_Request $request ) {
		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$content = sanitize_textarea_field( $request->get_param( 'content' ) ?? '' );
		$content = substr( $content, 0, (int) apply_filters( 'wpagent_user_profile_max_length', 4000, $agent['slug'] ) );
		$structured = $request->get_param( 'structured' );
		$saved = $this->repository->save_user_profile_memory( get_current_user_id(), $agent['slug'], $content, is_array( $structured ) ? $structured : array(), $agent['user_profile_fields'] ?? array() );

		if ( ! $saved ) {
			return new WP_Error( 'wpagent_user_profile_not_saved', __( 'Nao foi possivel salvar o perfil do usuario.', 'wpagent' ), array( 'status' => 500 ) );
		}

		$profile = $this->repository->get_user_profile_data( get_current_user_id(), $agent['slug'] );

		return rest_ensure_response(
			array(
				'saved'   => true,
				'content' => $profile['free_text'],
				'structured' => $profile['structured'],
			)
		);
	}

	public function list_conversations( WP_REST_Request $request ) {
		$agent      = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$project_id = $request->get_param( 'project_id' );

		return rest_ensure_response(
			array(
				'conversations' => $this->repository->list_conversations( get_current_user_id(), $agent['slug'], 50, $project_id ),
			)
		);
	}

	public function create_conversation( WP_REST_Request $request ) {
		$agent      = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$title      = sanitize_text_field( $request->get_param( 'title' ) ?: __( 'Nova conversa', 'wpagent' ) );
		$project_id = $request->get_param( 'project_id' ) ? absint( $request->get_param( 'project_id' ) ) : null;

		return rest_ensure_response(
			array(
				'conversation' => $this->repository->create_conversation( get_current_user_id(), $agent['slug'], $title, '', $project_id ),
			)
		);
	}

	public function list_projects( WP_REST_Request $request ) {
		$agent   = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$user_id = get_current_user_id();

		return rest_ensure_response(
			array(
				'projects' => $this->repository->list_projects( $user_id, $agent['slug'] ),
			)
		);
	}

	public function create_project( WP_REST_Request $request ) {
		$agent        = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$name         = sanitize_text_field( $request->get_param( 'name' ) ?: __( 'Novo projeto', 'wpagent' ) );
		$profile_data = $request->get_param( 'profile_data' );
		$user_id      = get_current_user_id();

		$project = $this->repository->create_project( $user_id, $agent['slug'], $name, is_array( $profile_data ) ? $profile_data : array() );

		return rest_ensure_response( array( 'project' => $project ) );
	}

	public function update_project( WP_REST_Request $request ) {
		$project_id = absint( $request['id'] );
		$agent      = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$user_id    = get_current_user_id();
		$data       = array();

		if ( null !== $request->get_param( 'name' ) ) {
			$data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'profile_data' ) ) {
			$p_data               = $request->get_param( 'profile_data' );
			$data['profile_data'] = is_array( $p_data ) ? $p_data : array();
		}

		$project = $this->repository->update_project( $project_id, $user_id, $agent['slug'], $data );

		return rest_ensure_response( array( 'project' => $project ) );
	}

	public function delete_project( WP_REST_Request $request ) {
		$project_id           = absint( $request['id'] );
		$agent                = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$delete_conversations = (bool) $request->get_param( 'delete_conversations' );
		$user_id              = get_current_user_id();

		$deleted = $this->repository->delete_project( $project_id, $user_id, $agent['slug'], $delete_conversations );

		return rest_ensure_response( array( 'deleted' => $deleted ) );
	}

	public function rename_conversation( WP_REST_Request $request ) {
		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$title = sanitize_text_field( $request->get_param( 'title' ) );

		if ( empty( $title ) ) {
			return new WP_Error( 'wpagent_empty_title', __( 'Informe um nome para a conversa.', 'wpagent' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'conversation' => $this->repository->update_conversation_title( $request['conversation_id'], get_current_user_id(), $agent['slug'], $title ),
			)
		);
	}

	public function get_conversation_messages( WP_REST_Request $request ) {
		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$messages = $this->repository->get_conversation_messages( $request['conversation_id'], get_current_user_id(), $agent['slug'] );

		return rest_ensure_response(
			array(
				'messages' => $messages,
			)
		);
	}

	public function delete_conversation( WP_REST_Request $request ) {
		$agent = $this->agents->get_agent( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$deleted = $this->repository->delete_conversation( $request['conversation_id'], get_current_user_id(), $agent['slug'] );

		if ( ! $deleted ) {
			return new WP_Error( 'wpagent_conversation_not_deleted', __( 'Nao foi possivel apagar esta conversa.', 'wpagent' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
			)
		);
	}

	private function conversation_title_from_message( $message ) {
		$title = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );

		if ( strlen( $title ) > 60 ) {
			$title = substr( $title, 0, 57 ) . '...';
		}

		return $title ?: __( 'Nova conversa', 'wpagent' );
	}

	private function knowledge_sources_for_metadata( $knowledge ) {
		$sources = array();

		foreach ( (array) $knowledge as $item ) {
			$sources[] = array(
				'id'      => absint( $item['id'] ?? 0 ),
				'title'   => sanitize_text_field( $item['title'] ?? '' ),
				'score'   => round( (float) ( $item['score'] ?? 0 ), 4 ),
				'strategy'=> sanitize_key( $item['strategy'] ?? '' ),
				'preview' => $this->compact_metadata_text( $item['content'] ?? '', 180 ),
			);
		}

		return $sources;
	}

	private function compact_metadata_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );

		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		return substr( $text, 0, $limit - 3 ) . '...';
	}

	private function check_agent_token_limits( $agent ) {
		$usage = $this->repository->get_token_usage_for_agent( $agent['slug'] );
		$limits = array(
			'day'   => absint( $agent['token_limit_day'] ?? 0 ),
			'week'  => absint( $agent['token_limit_week'] ?? 0 ),
			'month' => absint( $agent['token_limit_month'] ?? 0 ),
		);

		foreach ( $limits as $period => $limit ) {
			if ( $limit > 0 && ( $usage[ $period ] ?? 0 ) >= $limit ) {
				return new WP_Error(
					'wpagent_token_limit_reached',
					$this->token_limit_message( $period, $limit ),
					array(
						'status' => 429,
						'usage'  => $usage,
						'limits' => $limits,
					)
				);
			}
		}

		return true;
	}

	private function check_guest_rate_limit( $agent_slug ) {
		$limit = (int) apply_filters( 'wpagent_guest_chat_rate_limit_per_minute', 20, $agent_slug );
		if ( $limit < 1 ) {
			return true;
		}

		$bucket = (string) floor( time() / MINUTE_IN_SECONDS );
		$key = 'wpagent_guest_rate_' . md5( $agent_slug . '|' . $this->client_ip_hash() . '|' . $bucket );
		$count = absint( get_transient( $key ) );

		if ( $count >= $limit ) {
			return new WP_Error(
				'wpagent_guest_rate_limited',
				__( 'Muitas mensagens em pouco tempo. Aguarde um minuto e tente novamente.', 'wpagent' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );

		return true;
	}

	private function client_ip_hash() {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		return wp_hash( $ip );
	}

	private function token_limit_message( $period, $limit ) {
		$labels = array(
			'day'   => __( 'diario', 'wpagent' ),
			'week'  => __( 'semanal', 'wpagent' ),
			'month' => __( 'mensal', 'wpagent' ),
		);

		return sprintf(
			/* translators: 1: period label, 2: token limit. */
			__( 'Este agente atingiu o limite %1$s de %2$s tokens. Tente novamente depois ou fale com o administrador.', 'wpagent' ),
			$labels[ $period ] ?? $period,
			number_format_i18n( $limit )
		);
	}

	public function upload_chat_file( WP_REST_Request $request ) {
		$agent_slug      = sanitize_key( $request->get_param( 'agent_slug' ) ?: 'default' );
		$agent           = $this->agents->get_agent( $agent_slug );
		$conversation_id = sanitize_text_field( $request->get_param( 'conversation_id' ) ?: '' );
		$project_id      = absint( $request->get_param( 'project_id' ) ?: 0 );
		$scope           = sanitize_key( $request->get_param( 'scope' ) ?: 'conversation' );

		if ( '1' !== ( $agent['enable_frontend_uploads'] ?? '0' ) ) {
			return new WP_Error( 'wpagent_upload_disabled', __( 'O envio de arquivos está desativado para este agente.', 'wpagent' ), array( 'status' => 403 ) );
		}

		if ( ! $this->user_can_access_agent( $agent ) ) {
			return new WP_Error( 'wpagent_forbidden', __( 'Você não tem permissão para acessar este agente.', 'wpagent' ), array( 'status' => 403 ) );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'wpagent_missing_file', __( 'Nenhum arquivo foi enviado.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$allowed_mimes = array(
			'txt'  => 'text/plain',
			'md'   => 'text/plain',
			'csv'  => 'text/csv',
			'json' => 'application/json',
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		$allowed_exts  = array_keys( $allowed_mimes );
		$filename      = sanitize_file_name( $file['name'] );
		$ext           = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return new WP_Error(
				'wpagent_invalid_file_type',
				sprintf(
					/* translators: %s: file extension */
					__( 'Extensão de arquivo não suportada: .%s. Envie TXT, MD, CSV, JSON, PDF ou DOCX.', 'wpagent' ),
					$ext
				),
				array( 'status' => 400 )
			);
		}

		$checked_type = wp_check_filetype_and_ext( $file['tmp_name'], $filename, $allowed_mimes );
		if ( empty( $checked_type['ext'] ) && ! in_array( $ext, array( 'txt', 'md', 'csv', 'json' ), true ) ) {
			return new WP_Error(
				'wpagent_invalid_file_mime',
				__( 'O conteúdo do arquivo não corresponde a um formato permitido (TXT, MD, CSV, JSON, PDF ou DOCX).', 'wpagent' ),
				array( 'status' => 400 )
			);
		}

		$max_size = (int) apply_filters( 'wpagent_frontend_upload_max_size', 10 * MB_IN_BYTES, $agent );
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'wpagent_file_too_large', __( 'O arquivo excede o tamanho máximo permitido.', 'wpagent' ), array( 'status' => 400 ) );
		}

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);
		$uploaded         = wp_handle_upload( $file, $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'wpagent_upload_error', $uploaded['error'], array( 'status' => 500 ) );
		}

		$attachment_data = array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) || empty( $attachment_id ) ) {
			return new WP_Error( 'wpagent_attachment_failed', __( 'Falha ao registrar anexo no WordPress.', 'wpagent' ), array( 'status' => 500 ) );
		}

		$indexer   = new WPAgent_Document_Indexer( $this->repository );
		$source_id = $this->repository->upsert_training_source(
			array(
				'agent_slug'     => $agent['slug'],
				'post_id'        => 0,
				'attachment_id'  => $attachment_id,
				'source_type'    => 'chat_upload',
				'title'          => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'filename'       => $filename,
				'url'            => $uploaded['url'],
				'mime'           => $uploaded['type'],
				'status'         => 'processing',
				'status_message' => __( 'Extraindo texto via chat.', 'wpagent' ),
				'metadata'       => wp_json_encode(
					array(
						'scope'           => 'project' === $scope ? 'project' : 'conversation',
						'conversation_id' => $conversation_id,
						'project_id'      => $project_id,
						'uploaded_by'     => get_current_user_id(),
					)
				),
			)
		);

		$extracted = $indexer->index_attachment( 0, $agent['slug'], $attachment_id );

		if ( $source_id ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'wpagent_sources',
				array(
					'metadata' => wp_json_encode(
						array(
							'scope'           => 'project' === $scope ? 'project' : 'conversation',
							'conversation_id' => $conversation_id,
							'project_id'      => $project_id,
							'uploaded_by'     => get_current_user_id(),
						)
					),
				),
				array( 'id' => $source_id )
			);
		}

		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'filename'   => $filename,
				'scope'      => 'project' === $scope ? 'project' : 'conversation',
				'message'    => sprintf(
					/* translators: %s: filename */
					__( 'Documento "%s" indexado com sucesso e disponível neste atendimento!', 'wpagent' ),
					$filename
				),
			)
		);
	}

	public function get_progress( WP_REST_Request $request ) {
		$agent_slug  = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$layout_type = sanitize_key( $request->get_param( 'layout_type' ) ?: 'steps' );
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) ?: '' );
		$user_id     = get_current_user_id();

		$progress = $this->repository->get_user_progress( $user_id, $agent_slug, $layout_type, $session_id );

		return rest_ensure_response(
			array(
				'progress' => $progress ?: array(
					'current_step'      => 0,
					'completed_steps'   => array(),
					'current_lesson'    => '',
					'completed_lessons' => array(),
					'final_report'      => '',
					'status'            => 'in_progress',
				),
			)
		);
	}

	public function save_progress( WP_REST_Request $request ) {
		$agent_slug  = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$layout_type = sanitize_key( $request->get_param( 'layout_type' ) ?: 'steps' );
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) ?: '' );
		$user_id     = get_current_user_id();

		$payload = array(
			'user_id'     => $user_id,
			'session_id'  => $session_id,
			'agent_slug'  => $agent_slug,
			'layout_type' => $layout_type,
		);

		if ( null !== $request->get_param( 'current_step' ) ) {
			$payload['current_step'] = absint( $request->get_param( 'current_step' ) );
		}
		if ( null !== $request->get_param( 'completed_steps' ) ) {
			$raw_steps = $request->get_param( 'completed_steps' );
			$clean_steps = array();
			if ( is_array( $raw_steps ) ) {
				foreach ( array_slice( $raw_steps, 0, 100, true ) as $step_key => $step_val ) {
					$idx = absint( $step_key );
					if ( is_array( $step_val ) ) {
						$clean_steps[ $idx ] = array(
							'step'     => absint( $step_val['step'] ?? ( $idx + 1 ) ),
							'title'    => substr( sanitize_text_field( (string) ( $step_val['title'] ?? '' ) ), 0, 300 ),
							'question' => substr( sanitize_text_field( (string) ( $step_val['question'] ?? '' ) ), 0, 1000 ),
							'answer'   => substr( sanitize_textarea_field( (string) ( $step_val['answer'] ?? '' ) ), 0, 10000 ),
						);
					} else {
						$clean_steps[ $idx ] = substr( sanitize_textarea_field( (string) $step_val ), 0, 10000 );
					}
				}
			}
			$payload['completed_steps'] = $clean_steps;
		}
		if ( null !== $request->get_param( 'current_lesson' ) ) {
			$payload['current_lesson'] = substr( sanitize_text_field( (string) $request->get_param( 'current_lesson' ) ), 0, 120 );
		}
		if ( null !== $request->get_param( 'completed_lessons' ) ) {
			$raw_lessons = $request->get_param( 'completed_lessons' );
			$clean_lessons = array();
			if ( is_array( $raw_lessons ) ) {
				foreach ( array_slice( $raw_lessons, 0, 500 ) as $lesson_id ) {
					$clean_id = substr( sanitize_text_field( (string) $lesson_id ), 0, 120 );
					if ( '' !== $clean_id ) {
						$clean_lessons[] = $clean_id;
					}
				}
			}
			$payload['completed_lessons'] = array_values( array_unique( $clean_lessons ) );
		}
		if ( null !== $request->get_param( 'final_report' ) ) {
			$payload['final_report'] = wp_kses_post( substr( (string) $request->get_param( 'final_report' ), 0, 100000 ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$status_val        = sanitize_key( $request->get_param( 'status' ) );
			$payload['status'] = in_array( $status_val, array( 'in_progress', 'completed' ), true ) ? $status_val : 'in_progress';
		}

		$saved = $this->repository->save_user_progress( $payload );

		return rest_ensure_response(
			array(
				'success'  => true,
				'progress' => $saved,
			)
		);
	}

	public function reset_progress( WP_REST_Request $request ) {
		$agent_slug  = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$layout_type = sanitize_key( $request->get_param( 'layout_type' ) ?: 'steps' );
		$session_id  = sanitize_text_field( $request->get_param( 'session_id' ) ?: '' );
		$user_id     = get_current_user_id();

		$this->repository->reset_user_progress( $user_id, $agent_slug, $layout_type, $session_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Progresso reiniciado com sucesso.', 'wpagent' ),
			)
		);
	}

	public function generate_steps_report( WP_REST_Request $request ) {
		$agent_slug = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$answers    = $request->get_param( 'answers' );
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) ?: '' );
		$user_id    = get_current_user_id();

		$agent = $this->agents->get_agent( $agent_slug );

		$formatted_answers = '';
		$sanitized_answers = array();
		if ( is_array( $answers ) ) {
			foreach ( array_slice( $answers, 0, 100, true ) as $idx => $ans ) {
				$step_title = substr( sanitize_text_field( (string) ( $ans['title'] ?? ( __( 'Etapa', 'wpagent' ) . ' ' . ( absint( $idx ) + 1 ) ) ) ), 0, 300 );
				$question   = substr( sanitize_text_field( (string) ( $ans['question'] ?? '' ) ), 0, 1000 );
				$answer     = substr( sanitize_textarea_field( (string) ( $ans['answer'] ?? __( '[Etapa pulada / sem resposta]', 'wpagent' ) ) ), 0, 10000 );
				$sanitized_answers[ absint( $idx ) ] = array(
					'step'     => absint( $ans['step'] ?? ( absint( $idx ) + 1 ) ),
					'title'    => $step_title,
					'question' => $question,
					'answer'   => $answer,
				);
				$formatted_answers .= sprintf( "### %s\n**Pergunta:** %s\n**Resposta do Usuário:** %s\n\n", $step_title, $question, $answer );
			}
		} elseif ( is_string( $answers ) ) {
			$formatted_answers = substr( sanitize_textarea_field( $answers ), 0, 50000 );
		}

		$instructions = ! empty( $agent['steps_final_prompt'] )
			? $agent['steps_final_prompt']
			: __( 'Com base em todas as respostas fornecidas pelo usuário nas etapas anteriores, elabore um relatório final completo, estruturado, profissional e detalhado com plano de ação e recomendações práticas.', 'wpagent' );

		$prompt = sprintf(
			"Você é o agente \"%s\".\n\nDiretrizes do Agente:\n%s\n\nRespostas coletadas no questionário passo a passo:\n%s\n\nInstruções para o Relatório Final:\n%s\n\nPor favor, redija agora o Relatório Final formatado em Markdown rico (com títulos, tópicos, destaques e tabelas se aplicável). Não inclua preâmbulos desnecessários.",
			$agent['name'],
			$agent['system_prompt'],
			$formatted_answers,
			$instructions
		);

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		try {
			$response = $this->ai_client->chat(
				$prompt,
				$user_id,
				$agent_slug,
				'steps-report-' . ( $user_id ? $user_id : $session_id ),
				$session_id,
				null,
				'steps-report'
			);
		} catch ( Throwable $throwable ) {
			$response = new WP_Error( 'wpagent_steps_report_error', $throwable->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$report = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;

		// Save progress as completed with final report
		$this->repository->save_user_progress(
			array(
				'user_id'         => $user_id,
				'session_id'      => $session_id,
				'agent_slug'      => $agent_slug,
				'layout_type'     => 'steps',
				'completed_steps' => $sanitized_answers,
				'final_report'    => wp_kses_post( $report ),
				'status'          => 'completed',
			)
		);

		$guest_lead = 0 === $user_id ? get_transient( 'wpagent_guest_lead_' . md5( $agent_slug . '|' . $session_id ) ) : null;
		$this->dispatch_webhook(
			$agent,
			'steps_report_generated',
			array(
				'user_id'    => $user_id,
				'session_id' => $session_id,
				'answers'    => $sanitized_answers,
				'report'     => $report,
				'lead'       => is_array( $guest_lead ) ? $guest_lead : null,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'report'  => $report,
			)
		);
	}

	public function generate_ead_syllabus( WP_REST_Request $request ) {
		$agent_slug   = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$course_title = sanitize_text_field( $request->get_param( 'course_title' ) ?: '' );
		$manual_text  = sanitize_textarea_field( $request->get_param( 'manual_text' ) ?: '' );
		$agent        = $this->agents->get_agent( $agent_slug );

		if ( empty( $course_title ) ) {
			$course_title = ! empty( $agent['ead_course_title'] ) ? $agent['ead_course_title'] : $agent['name'];
		}

		// Retrieve all indexed documents & training materials for this agent
		$materials_text = $this->repository->get_agent_training_content_summary( $agent_slug, 45000 );

		if ( ! empty( $manual_text ) ) {
			$materials_text .= "\n\n### Material Didático Adicional Fornecido:\n" . $manual_text;
		}

		if ( empty( trim( $materials_text ) ) ) {
			return new WP_Error(
				'wpagent_no_materials',
				__( 'Nenhum material didático ou documento foi encontrado na base deste agente. Por favor, anexe suas apostilas, PDFs ou textos na aba "Treinamentos" e salve o agente antes de gerar a ementa.', 'wpagent' ),
				array( 'status' => 400 )
			);
		}

		$system_instruction = "Você é um coordenador pedagógico e especialista sênior em design instrucional e criação de cursos EAD. Sua especialidade é analisar materiais didáticos, apostilas e documentos de treinamento para extrair uma estrutura curricular (ementa) completa, didática e perfeitamente ordenada em Módulos e Aulas.";

		$user_prompt = sprintf(
			"Analise detalhadamente o MATERIAL DIDÁTICO E DOCUMENTOS ANEXADOS abaixo e elabore a EMENTA COMPLETA de módulos e aulas para o curso \"%s\".\n\n" .
			"DIRETRIZES FUNDAMENTAIS:\n" .
			"1. Extraia os temas, capítulos, conceitos e tópicos DIRETAMENTE do material didático anexado. O curso deve ser totalmente fiel aos conteúdos reais do material.\n" .
			"2. Agrupe os tópicos em Módulos lógicos sequenciais (ex.: Módulo 1, Módulo 2, etc.).\n" .
			"3. Em cada módulo, liste as Aulas numeradas (ex.: Aula 1.1, Aula 1.2, Aula 2.1, etc.).\n" .
			"4. Após o título de cada aula, adicione entre parênteses um resumo dos tópicos e conceitos-chave que serão tratados nela.\n" .
			"5. Retorne EXCLUSIVAMENTE a lista formatada no padrão abaixo, sem saudações, sem explicações antes ou depois e sem blocos de código Markdown:\n\n" .
			"Módulo 1: Nome do Módulo\n" .
			"Aula 1.1: Título da Aula (Tópico 1; Tópico 2; Tópico 3)\n" .
			"Aula 1.2: Título da Aula (Tópico 4; Tópico 5)\n\n" .
			"Módulo 2: Nome do Módulo\n" .
			"Aula 2.1: Título da Aula (Tópico 6; Tópico 7)\n" .
			"Aula 2.2: Título da Aula (Tópico 8; Tópico 9)\n\n" .
			"--- MATERIAL DIDÁTICO E DOCUMENTOS ANEXADOS ---\n%s\n--- FIM DO MATERIAL DIDÁTICO ---",
			$course_title,
			trim( $materials_text )
		);

		try {
			$response = $this->ai_client->raw_prompt(
				$user_prompt,
				$agent_slug,
				$system_instruction
			);
		} catch ( Throwable $throwable ) {
			return new WP_Error( 'wpagent_syllabus_error', $throwable->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw_reply = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;
		$clean_syllabus = trim( $raw_reply );

		// Strip markdown code fences if present
		$clean_syllabus = preg_replace( '/^```[a-z0-9_-]*\s*/i', '', $clean_syllabus );
		$clean_syllabus = preg_replace( '/\s*```$/', '', $clean_syllabus );
		$clean_syllabus = trim( $clean_syllabus );

		return rest_ensure_response(
			array(
				'success'  => true,
				'syllabus' => $clean_syllabus,
			)
		);
	}

	public function generate_ead_lesson( WP_REST_Request $request ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$agent_slug       = sanitize_key( $request->get_param( 'agent_slug' ) ?: $this->settings->get( 'agent_slug', 'default' ) );
		$module_title     = sanitize_text_field( $request->get_param( 'module_title' ) ?: '' );
		$lesson_title     = sanitize_text_field( $request->get_param( 'lesson_title' ) ?: '' );
		$force_regenerate = ! empty( $request->get_param( 'force_regenerate' ) );
		$user_id          = get_current_user_id();
		$session_id       = sanitize_text_field( $request->get_param( 'session_id' ) ?: '' );

		if ( $force_regenerate && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'wpagent_forbidden_regenerate',
				__( 'Apenas administradores ou editores podem regerar o conteúdo das aulas.', 'wpagent' ),
				array( 'status' => 403 )
			);
		}

		$lesson_key = md5( $module_title . '|' . $lesson_title );

		// Check if lesson content is already generated and cached in DB
		if ( ! $force_regenerate ) {
			$cached_lesson = $this->repository->get_ead_lesson( $agent_slug, $lesson_key );
			if ( $cached_lesson && ! empty( $cached_lesson['content'] ) ) {
				return rest_ensure_response(
					array(
						'success' => true,
						'cached'  => true,
						'content' => $cached_lesson['content'],
					)
				);
			}
		}

		$agent = $this->agents->get_agent( $agent_slug );

		// Retrieve relevant training chunks for this specific lesson
		$relevant_chunks = $this->repository->search_training_chunks( $agent_slug, $module_title . ' ' . $lesson_title, 6 );
		$chunks_context = '';
		if ( ! empty( $relevant_chunks ) ) {
			$chunks_context = "\n\n--- TRECHOS RELEVANTES DO MATERIAL DIDÁTICO ---\n";
			foreach ( $relevant_chunks as $c ) {
				$heading_label   = ! empty( $c['heading'] ) ? "[{$c['heading']}]: " : "";
				$chunks_context .= "- " . $heading_label . $c['content'] . "\n\n";
			}
			$chunks_context .= "--- FIM DOS TRECHOS DO MATERIAL ---\n" .
				"Baseie a explicação teórica, conceitos e exemplos rigorosamente nos trechos acima.";
		}

		$prompt = sprintf(
			"Você é o instrutor do curso \"%s\".\n\nEscreva o CONTEÚDO COMPLETO e DIDÁTICO para a seguinte aula:\n- Módulo: %s\n- Aula: %s\n\nO texto da aula deve ser estruturado em Markdown com:\n1. 🎯 Objetivos da Aula\n2. 📖 Conteúdo Teórico e Explicação Detalhada\n3. 💡 Exemplos Práticos e Dicas\n4. 📝 Resumo dos Pontos-Chave\n\nUtilize a base de conhecimento e diretrizes do agente para redigir o conteúdo mais preciso possível. Responda diretamente com o conteúdo da aula formatado.%s",
			! empty( $agent['ead_course_title'] ) ? $agent['ead_course_title'] : $agent['name'],
			$module_title,
			$lesson_title,
			$chunks_context
		);

		try {
			$response = $this->ai_client->chat(
				$prompt,
				$user_id,
				$agent_slug,
				'ead-lesson-' . $lesson_key,
				$session_id,
				null,
				'ead-lesson'
			);
		} catch ( Throwable $throwable ) {
			return new WP_Error( 'wpagent_lesson_error', $throwable->getMessage(), array( 'status' => 500 ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;
		$clean_content = trim( $content );

		// Save permanently to database for instant future retrieval
		$this->repository->save_ead_lesson( $agent_slug, $lesson_key, $module_title, $lesson_title, $clean_content );

		return rest_ensure_response(
			array(
				'success' => true,
				'cached'  => false,
				'content' => $clean_content,
			)
		);
	}
}
}
