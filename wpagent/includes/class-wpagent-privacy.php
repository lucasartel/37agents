<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Privacy' ) ) {
class WPAgent_Privacy {

	public function register() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<h2>' . esc_html__( 'Assistentes de IA (37agents)', 'wpagent' ) . '</h2>'
			. '<p>' . esc_html__( 'Este site utiliza o plugin 37agents para oferecer atendimento por Inteligência Artificial, fluxos passo a passo e ambientes de aprendizado.', 'wpagent' ) . '</p>'
			. '<p>' . esc_html__( 'Dados armazenados localmente no WordPress: para usuários autenticados, armazenamos o histórico de conversas, interações, memórias de perfil fornecidas voluntariamente, projetos e progresso em questionários ou cursos (Steps/EAD). Para visitantes anônimos, identificadores temporários de sessão podem ser salvos no navegador via localStorage para manter a continuidade da sessão.', 'wpagent' ) . '</p>'
			. '<p>' . esc_html__( 'Envio a provedores externos de IA: as mensagens enviadas no chat são transmitidas aos provedores configurados pelo administrador (como OpenRouter ou conectores oficiais do WordPress AI) para geração das respostas.', 'wpagent' ) . '</p>'
			. '<p>' . esc_html__( 'Captura de leads e Webhooks: caso o formulário de contato esteja habilitado em algum agente, nome, e-mail e resumos gerados podem ser enviados a serviços externos de CRM ou automação via Webhook configurado pelo administrador.', 'wpagent' ) . '</p>';

		wp_add_privacy_policy_content( '37agents', wp_kses_post( wpautop( $content ) ) );
	}

	public function register_exporter( $exporters ) {
		$exporters['37agents-conversations'] = array(
			'exporter_friendly_name' => __( '37agents — Conversas', 'wpagent' ),
			'callback'               => array( $this, 'export_conversations' ),
		);
		$exporters['37agents-interactions'] = array(
			'exporter_friendly_name' => __( '37agents — Mensagens e Interações', 'wpagent' ),
			'callback'               => array( $this, 'export_interactions' ),
		);
		$exporters['37agents-memories'] = array(
			'exporter_friendly_name' => __( '37agents — Memórias e Perfil', 'wpagent' ),
			'callback'               => array( $this, 'export_memories' ),
		);
		$exporters['37agents-progress'] = array(
			'exporter_friendly_name' => __( '37agents — Progresso (Steps e EAD)', 'wpagent' ),
			'callback'               => array( $this, 'export_progress' ),
		);

		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers['37agents-personal-data'] = array(
			'eraser_friendly_name' => __( '37agents — Dados de Conversas, Memórias e Progresso', 'wpagent' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);

		return $erasers;
	}

	public function export_conversations( $email_address, $page = 1 ) {
		return $this->export_table_rows(
			$email_address,
			$page,
			'wpagent_conversations',
			'37agents-conversations',
			__( '37agents — Conversas', 'wpagent' ),
			static function ( $row ) {
				return array(
					array(
						'name'  => __( 'Agente', 'wpagent' ),
						'value' => $row['agent_slug'] ?? '',
					),
					array(
						'name'  => __( 'Título', 'wpagent' ),
						'value' => $row['title'] ?? '',
					),
					array(
						'name'  => __( 'ID da Conversa', 'wpagent' ),
						'value' => $row['conversation_id'] ?? '',
					),
					array(
						'name'  => __( 'Criada em', 'wpagent' ),
						'value' => $row['created_at'] ?? '',
					),
				);
			}
		);
	}

	public function export_interactions( $email_address, $page = 1 ) {
		return $this->export_table_rows(
			$email_address,
			$page,
			'wpagent_interactions',
			'37agents-interactions',
			__( '37agents — Mensagens e Interações', 'wpagent' ),
			static function ( $row ) {
				return array(
					array(
						'name'  => __( 'Agente', 'wpagent' ),
						'value' => $row['agent_slug'] ?? '',
					),
					array(
						'name'  => __( 'Mensagem do Usuário', 'wpagent' ),
						'value' => $row['message'] ?? '',
					),
					array(
						'name'  => __( 'Resposta do Agente', 'wpagent' ),
						'value' => $row['reply'] ?? '',
					),
					array(
						'name'  => __( 'Data', 'wpagent' ),
						'value' => $row['created_at'] ?? '',
					),
				);
			}
		);
	}

	public function export_memories( $email_address, $page = 1 ) {
		return $this->export_table_rows(
			$email_address,
			$page,
			'wpagent_memories',
			'37agents-memories',
			__( '37agents — Memórias e Perfil', 'wpagent' ),
			static function ( $row ) {
				return array(
					array(
						'name'  => __( 'Agente', 'wpagent' ),
						'value' => $row['agent_slug'] ?? '',
					),
					array(
						'name'  => __( 'Tipo', 'wpagent' ),
						'value' => $row['memory_type'] ?? '',
					),
					array(
						'name'  => __( 'Conteúdo', 'wpagent' ),
						'value' => $row['content'] ?? '',
					),
					array(
						'name'  => __( 'Atualizado em', 'wpagent' ),
						'value' => $row['updated_at'] ?? '',
					),
				);
			}
		);
	}

	public function export_progress( $email_address, $page = 1 ) {
		return $this->export_table_rows(
			$email_address,
			$page,
			'wpagent_user_progress',
			'37agents-progress',
			__( '37agents — Progresso (Steps e EAD)', 'wpagent' ),
			static function ( $row ) {
				return array(
					array(
						'name'  => __( 'Agente', 'wpagent' ),
						'value' => $row['agent_slug'] ?? '',
					),
					array(
						'name'  => __( 'Layout', 'wpagent' ),
						'value' => $row['layout_type'] ?? '',
					),
					array(
						'name'  => __( 'Status', 'wpagent' ),
						'value' => $row['status'] ?? '',
					),
					array(
						'name'  => __( 'Relatório Final / Dados', 'wpagent' ),
						'value' => ! empty( $row['final_report'] ) ? wp_strip_all_tags( $row['final_report'] ) : ( $row['completed_steps'] ?: $row['completed_lessons'] ?: '' ),
					),
					array(
						'name'  => __( 'Atualizado em', 'wpagent' ),
						'value' => $row['updated_at'] ?? '',
					),
				);
			}
		);
	}

	private function export_table_rows( $email_address, $page, $table_suffix, $group_id, $group_label, $mapper ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$table    = $wpdb->prefix . $table_suffix;
		$per_page = 100;
		$page     = max( 1, absint( $page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				absint( $user->ID ),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$export_items = array();
		foreach ( (array) $rows as $row ) {
			$export_items[] = array(
				'group_id'    => $group_id,
				'group_label' => $group_label,
				'item_id'     => $group_id . '-' . absint( $row['id'] ),
				'data'        => $mapper( $row ),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( (array) $rows ) < $per_page,
		);
	}

	public function erase_user_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$user_id       = absint( $user->ID );
		$items_removed = false;

		$tables = array(
			'wpagent_interactions',
			'wpagent_conversations',
			'wpagent_memories',
			'wpagent_user_progress',
			'wpagent_user_tokens_usage',
			'wpagent_projects',
		);

		foreach ( $tables as $suffix ) {
			$table  = $wpdb->prefix . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				continue;
			}

			$deleted = $wpdb->delete(
				$table,
				array( 'user_id' => $user_id ),
				array( '%d' )
			);

			if ( $deleted && $deleted > 0 ) {
				$items_removed = true;
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
}
