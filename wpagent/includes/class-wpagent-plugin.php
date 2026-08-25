<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Plugin' ) ) {
final class WPAgent_Plugin {
	private static $instance = null;

	private $repository;
	private $settings;
	private $wordpress_ai_integration;
	private $agents;
	private $reports;
	private $embeddings;
	private $periodic_tasks;
	private $admin_abilities;
	private $email_actions;
	private $email_schedules;
	private $conversation_summaries;
	private $ai_client;
	private $rest_controller;
	private $shortcode;
	private $comment_suggest;
	private $bulk_summary;
	private $content_provenance;
	private $comment_moderation;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->dashboard       = new WPAgent_Dashboard();
		$this->repository      = new WPAgent_Repository();
		$this->settings        = new WPAgent_Settings();
		$this->wordpress_ai_integration = new WPAgent_WordPress_AI_Integration( $this->settings );
		$this->agents          = new WPAgent_Agents( $this->settings, $this->repository );
		$this->reports         = new WPAgent_Reports( $this->repository, $this->agents );
		$this->embeddings      = new WPAgent_Embeddings( $this->repository, $this->settings );
		$this->admin_abilities = new WPAgent_Admin_Abilities( $this->repository, $this->settings );
		$this->email_actions          = new WPAgent_Email_Actions( $this->repository );
		$this->email_schedules        = new WPAgent_Email_Schedules( $this->repository, $this->settings, $this->agents );
		$this->conversation_summaries = new WPAgent_Conversation_Summaries( $this->repository, $this->settings, $this->agents );
		$this->ai_client              = new WPAgent_AI_Client( $this->repository, $this->settings, $this->agents, $this->embeddings, $this->admin_abilities, $this->email_actions, $this->email_schedules );
		$this->periodic_tasks         = new WPAgent_Periodic_Tasks( $this->repository, $this->settings, $this->agents, $this->ai_client );
		$this->rest_controller = new WPAgent_REST_Controller( $this->repository, $this->settings, $this->agents, $this->ai_client, $this->admin_abilities, $this->email_actions, $this->email_schedules );
		$this->shortcode       = new WPAgent_Shortcode( $this->settings, $this->agents, $this->repository );
		$this->comment_suggest    = new WPAgent_Comment_Suggest( $this->settings, $this->agents, $this->ai_client );
		$this->bulk_summary       = new WPAgent_Bulk_Summary( $this->settings, $this->agents, $this->ai_client );
		$this->content_provenance = new WPAgent_Content_Provenance();
		$this->comment_moderation = new WPAgent_Comment_Moderation( $this->repository, $this->settings, $this->agents, $this->ai_client );
	}

	public function boot() {
		if ( get_option( 'wpagent_schema_version' ) !== WPAGENT_VERSION ) {
			WPAgent_Activator::activate();
		}
		if ( $this->repository && method_exists( $this->repository, 'ensure_projects_table_exists' ) ) {
			$this->repository->ensure_projects_table_exists();
		}
		if ( $this->repository && method_exists( $this->repository, 'ensure_email_schedules_tables_exist' ) ) {
			$this->repository->ensure_email_schedules_tables_exist();
		}
		if ( $this->repository && method_exists( $this->repository, 'ensure_periodic_contents_table_exists' ) ) {
			$this->repository->ensure_periodic_contents_table_exists();
		}
		$this->dashboard->register();
		$this->wordpress_ai_integration->register();
		$this->settings->register();
		$this->agents->register();
		$this->reports->register();
		$this->embeddings->register();
		$this->periodic_tasks->register();
		$this->admin_abilities->register();
		$this->email_actions->register();
		$this->email_schedules->register();
		$this->conversation_summaries->register();
		$this->shortcode->register();
		$this->comment_suggest->register();
		$this->bulk_summary->register();
		$this->content_provenance->register();
		$this->comment_moderation->register();

		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( 'WPAgent_Admin_UI', 'enqueue_styles' ) );
	}
}
}
