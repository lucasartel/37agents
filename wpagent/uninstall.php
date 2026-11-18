<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'wpagent_interactions',
	'wpagent_conversations',
	'wpagent_projects',
	'wpagent_memories',
	'wpagent_sources',
	'wpagent_source_chunks',
	'wpagent_chunk_embeddings',
	'wpagent_email_events',
	'wpagent_email_schedules',
	'wpagent_email_subscriptions',
	'wpagent_user_tokens_usage',
	'wpagent_conversation_summaries',
	'wpagent_periodic_contents',
	'wpagent_user_progress',
	'wpagent_ead_lessons',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

$cron_hooks = array(
	'wpagent_process_embedding_batch',
	'wpagent_process_periodic_tasks',
	'wpagent_send_email_events',
	'wpagent_process_email_schedules',
	'wpagent_process_conversation_summaries',
	'wpagent_comment_moderation_cron',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$options = array(
	'wpagent_settings',
	'wpagent_schema_version',
	'wpagent_embedding_status',
	'wpagent_default_agent_post_id',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$meta_keys = array(
	'_wpagent_agent_slug',
	'_wpagent_provider_mode',
	'_wpagent_compatible_provider_preset',
	'_wpagent_compatible_api_base_url',
	'_wpagent_compatible_model',
	'_wpagent_openrouter_model',
	'_wpagent_wordpress_ai_provider',
	'_wpagent_wordpress_ai_model',
	'_wpagent_use_wp_ai_client',
	'_wpagent_allow_guest_chat',
	'_wpagent_public_site_assistant',
	'_wpagent_admin_assistant',
	'_wpagent_user_profile_enabled',
	'_wpagent_user_profile_label',
	'_wpagent_user_profile_description',
	'_wpagent_user_profile_fields',
	'_wpagent_email_actions_enabled',
	'_wpagent_email_actions_instructions',
	'_wpagent_system_prompt',
	'_wpagent_max_knowledge_items',
	'_wpagent_max_memory_items',
	'_wpagent_max_recent_interactions',
	'_wpagent_token_limit_day',
	'_wpagent_token_limit_week',
	'_wpagent_token_limit_month',
	'_wpagent_show_token_usage',
	'_wpagent_site_context_enabled',
	'_wpagent_conversation_summary_enabled',
	'_wpagent_conversation_summary_delay',
	'_wpagent_email_schedules_enabled',
	'_wpagent_default_theme',
	'_wpagent_frontend_layout',
	'_wpagent_steps_intro_title',
	'_wpagent_steps_intro',
	'_wpagent_steps_intro_description',
	'_wpagent_steps_questions',
	'_wpagent_steps_final_prompt',
	'_wpagent_steps_button_label',
	'_wpagent_ead_course_title',
	'_wpagent_ead_syllabus',
	'_wpagent_allowed_roles',
	'_wpagent_enable_projects',
	'_wpagent_enable_frontend_uploads',
	'_wpagent_auto_rag_post_types',
	'_wpagent_privacy_consent_enabled',
	'_wpagent_privacy_consent_text',
	'_wpagent_lead_capture_enabled',
	'_wpagent_webhook_enabled',
	'_wpagent_webhook_url',
);

foreach ( $meta_keys as $key ) {
	delete_post_meta_by_key( $key );
}

$agent_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpagent_agent' ) );
foreach ( $agent_ids as $agent_id ) {
	wp_delete_post( (int) $agent_id, true );
}
