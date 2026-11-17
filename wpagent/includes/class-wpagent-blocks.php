<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Blocks' ) ) {
class WPAgent_Blocks {
	private $agents;
	private $shortcode;

	public function __construct( WPAgent_Agents $agents, WPAgent_Shortcode $shortcode ) {
		$this->agents    = $agents;
		$this->shortcode = $shortcode;
	}

	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_editor_assets' ) );
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'wpagent-block-editor',
			WPAGENT_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			WPAGENT_VERSION,
			true
		);

		register_block_type(
			'37agents/agent-widget',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpagent-block-editor',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'agent'  => array(
						'type'    => 'string',
						'default' => 'default',
					),
					'layout' => array(
						'type'    => 'string',
						'default' => '',
					),
					'theme'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'title'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public function localize_editor_assets() {
		$options = $this->agents->get_agent_options();
		$agents  = array();

		foreach ( $options as $slug => $name ) {
			$agents[] = array(
				'value' => sanitize_key( $slug ),
				'label' => sanitize_text_field( $name ) . ' (' . sanitize_key( $slug ) . ')',
			);
		}

		wp_localize_script(
			'wpagent-block-editor',
			'wpagentBlockData',
			array(
				'agents' => $agents,
				'i18n'   => array(
					'blockTitle'       => __( '37agents — Assistente de IA', 'wpagent' ),
					'blockDescription' => __( 'Incorpora um agente de IA (Chat, Steps ou Curso EAD) do 37agents.', 'wpagent' ),
					'settingsHeading'  => __( 'Configurações do Agente', 'wpagent' ),
					'agentLabel'       => __( 'Agente', 'wpagent' ),
					'layoutLabel'      => __( 'Layout de Interface', 'wpagent' ),
					'layoutDefault'    => __( 'Usar padrão do agente', 'wpagent' ),
					'layoutChat'       => __( 'Chat Conversacional', 'wpagent' ),
					'layoutSteps'      => __( 'Passo a Passo (Steps)', 'wpagent' ),
					'layoutEad'        => __( 'Curso EAD (Módulos & Aulas)', 'wpagent' ),
					'themeLabel'       => __( 'Tema Visual', 'wpagent' ),
					'themeDefault'     => __( 'Usar padrão do agente', 'wpagent' ),
					'themeLight'       => __( 'Modo Claro', 'wpagent' ),
					'themeDark'        => __( 'Modo Escuro', 'wpagent' ),
					'titleLabel'       => __( 'Título personalizado (opcional)', 'wpagent' ),
					'previewHint'      => __( 'Selecione o agente, layout e tema no painel lateral direito.', 'wpagent' ),
				),
			)
		);
	}

	public function render_block( $attributes ) {
		$atts = array(
			'agent'  => sanitize_key( $attributes['agent'] ?? 'default' ),
			'layout' => sanitize_key( $attributes['layout'] ?? '' ),
			'theme'  => sanitize_key( $attributes['theme'] ?? '' ),
			'title'  => sanitize_text_field( $attributes['title'] ?? '' ),
		);

		return $this->shortcode->render( $atts );
	}
}
}
