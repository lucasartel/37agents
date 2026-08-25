<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPAgent_Bulk_Summary' ) ) {
class WPAgent_Bulk_Summary {
    private $settings;
    private $agents;
    private $ai_client;

    public function __construct( WPAgent_Settings $settings, WPAgent_Agents $agents, WPAgent_AI_Client $ai_client ) {
        $this->settings  = $settings;
        $this->agents    = $agents;
        $this->ai_client = $ai_client;
    }

    public function register() {
        add_filter( 'bulk_actions-edit-post', array( $this, 'register_bulk_action' ) );
        add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
    }

    public function register_bulk_action( $bulk_actions ) {
        $bulk_actions['wpagent_bulk_summarize'] = __( 'Gerar resumo com IA', 'wpagent' );
        return $bulk_actions;
    }

    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        if ( 'wpagent_bulk_summarize' !== $action ) {
            return $redirect_to;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return $redirect_to;
        }

        $processed = 0;
        $failed    = 0;

        foreach ( $post_ids as $post_id ) {
            $result = $this->generate_summary_for_post( absint( $post_id ) );
            if ( is_wp_error( $result ) ) {
                $failed++;
            } else {
                $processed++;
            }
        }

        return add_query_arg(
            array(
                'wpagent_summarized' => $processed,
                'wpagent_sum_failed' => $failed,
            ),
            $redirect_to
        );
    }

    public function bulk_action_notice() {
        if ( ! empty( $_REQUEST['wpagent_summarized'] ) ) {
            $count = absint( $_REQUEST['wpagent_summarized'] );
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                /* translators: %d: number of posts */
                esc_html( _n( 'Resumo gerado para %d post.', 'Resumos gerados para %d posts.', $count, 'wpagent' ) ),
                $count
            );
            echo '</p></div>';
        }
        if ( ! empty( $_REQUEST['wpagent_sum_failed'] ) ) {
            $count = absint( $_REQUEST['wpagent_sum_failed'] );
            echo '<div class="notice notice-error is-dismissible"><p>';
            printf(
                /* translators: %d: number of failures */
                esc_html( _n( 'Falha ao gerar resumo para %d post.', 'Falha ao gerar resumo para %d posts.', $count, 'wpagent' ) ),
                $count
            );
            echo '</p></div>';
        }
    }

    private function generate_summary_for_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'wpagent_forbidden', __( 'Sem permissão para editar este post.', 'wpagent' ) );
        }

        $content = wp_strip_all_tags( $post->post_content );
        if ( empty( trim( $content ) ) ) {
            return new WP_Error( 'wpagent_empty', __( 'Post sem conteúdo para resumir.', 'wpagent' ) );
        }

        // Limit content to ~3000 chars to avoid token overflow
        $content = mb_substr( $content, 0, 3000 );

        $prompt = sprintf(
            /* translators: 1: post title, 2: post content */
            __( 'Crie um resumo conciso (máximo 2-3 frases) para o seguinte artigo chamado "%1$s":\n\n%2$s\n\nRetorne APENAS o resumo, sem introduções ou rótulos.', 'wpagent' ),
            get_the_title( $post ),
            $content
        );

        $agent_slug = $this->settings->get( 'default_agent_slug', 'default' );
        $user_id    = get_current_user_id() ?: 1;

        $response = $this->ai_client->chat(
            $prompt,
            $user_id,
            $agent_slug,
            'bulk-summary-' . $post_id,
            'bulk-summary'
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $summary = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;
        $summary = wp_strip_all_tags( trim( $summary ) );

        if ( empty( $summary ) ) {
            return new WP_Error( 'wpagent_empty_response', __( 'A IA retornou um resumo vazio.', 'wpagent' ) );
        }

        wp_update_post( array(
            'ID'           => $post_id,
            'post_excerpt' => $summary,
        ) );

        return true;
    }

    public function register_rest_route() {
        register_rest_route(
            'wpagent/v1',
            '/summarize-post',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_rest_summarize' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
                'args' => array(
                    'post_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    public function handle_rest_summarize( WP_REST_Request $request ) {
        $result = $this->generate_summary_for_post( $request->get_param( 'post_id' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( array( 'success' => true ) );
    }
}
}
