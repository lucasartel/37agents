<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPAgent_Comment_Suggest' ) ) {
class WPAgent_Comment_Suggest {
    private $settings;
    private $agents;
    private $ai_client;

    public function __construct( WPAgent_Settings $settings, WPAgent_Agents $agents, WPAgent_AI_Client $ai_client ) {
        $this->settings  = $settings;
        $this->agents    = $agents;
        $this->ai_client = $ai_client;
    }

    public function register() {
        if ( is_admin() ) {
            add_filter( 'comment_row_actions', array( $this, 'add_comment_action' ), 10, 2 );
            add_action( 'admin_footer-edit-comments.php', array( $this, 'render_reply_modal' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        }
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'edit-comments.php' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'wpagent-comment-suggest',
            plugins_url( 'assets/js/comment-suggest.js', WPAGENT_FILE ),
            array( 'jquery' ),
            WPAGENT_VERSION,
            true
        );
        wp_localize_script( 'wpagent-comment-suggest', 'wpagentCommentSuggest', array(
            'restUrl' => rest_url( 'wpagent/v1/suggest-reply' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'l10n'    => array(
                'generating' => __( 'Gerando sugestão...', 'wpagent' ),
                'error'      => __( 'Erro ao gerar sugestão. Tente novamente.', 'wpagent' ),
                'suggestBtn' => __( 'Sugerir resposta com IA', 'wpagent' ),
            ),
        ) );
    }

    public function add_comment_action( $actions, $comment ) {
        if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
            return $actions;
        }
        $actions['wpagent_suggest'] = sprintf(
            '<a href="#" class="wpagent-suggest-reply" data-comment-id="%d" data-post-id="%d">%s</a>',
            absint( $comment->comment_ID ),
            absint( $comment->comment_post_ID ),
            esc_html__( 'Sugerir resposta com IA', 'wpagent' )
        );
        return $actions;
    }

    public function render_reply_modal() {
        ?>
        <div id="wpagent-suggest-modal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:24px;z-index:999999;width:500px;max-width:90vw;box-shadow:0 4px 24px rgba(0,0,0,.2);border-radius:8px;">
            <h3><?php esc_html_e( 'Sugestão de Resposta por IA', 'wpagent' ); ?></h3>
            <div id="wpagent-suggest-status"></div>
            <textarea id="wpagent-suggest-text" rows="6" style="width:100%;margin:12px 0;"></textarea>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button id="wpagent-suggest-use" class="button button-primary" disabled><?php esc_html_e( 'Usar esta resposta', 'wpagent' ); ?></button>
                <button id="wpagent-suggest-close" class="button"><?php esc_html_e( 'Fechar', 'wpagent' ); ?></button>
            </div>
        </div>
        <div id="wpagent-suggest-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999998;"></div>
        <?php
    }

    public function register_rest_route() {
        register_rest_route(
            'wpagent/v1',
            '/suggest-reply',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_suggest_reply' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
                'args' => array(
                    'comment_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'tone' => array(
                        'type'              => 'string',
                        'default'           => 'friendly',
                        'sanitize_callback' => 'sanitize_key',
                        'enum'              => array( 'friendly', 'professional', 'casual' ),
                    ),
                ),
            )
        );
    }

    public function handle_suggest_reply( WP_REST_Request $request ) {
        $comment_id = $request->get_param( 'comment_id' );
        $tone       = $request->get_param( 'tone' );

        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            return new WP_Error( 'wpagent_not_found', __( 'Comentário não encontrado.', 'wpagent' ), array( 'status' => 404 ) );
        }

        $post = get_post( $comment->comment_post_ID );
        $tone_labels = array(
            'friendly'     => __( 'amigável e acolhedor', 'wpagent' ),
            'professional' => __( 'profissional e formal', 'wpagent' ),
            'casual'       => __( 'casual e descontraído', 'wpagent' ),
        );
        $tone_label = $tone_labels[ $tone ] ?? $tone_labels['friendly'];

        $prompt = sprintf(
            /* translators: 1: post title, 2: comment author, 3: comment content, 4: tone label */
            __( 'Você está respondendo um comentário no post "%1$s".\n\nComentário de %2$s:\n%3$s\n\nEscreva uma resposta %4$s, concisa e útil para este comentário. Responda diretamente, sem preâmbulos.', 'wpagent' ),
            $post ? get_the_title( $post ) : '',
            sanitize_text_field( $comment->comment_author ),
            sanitize_textarea_field( $comment->comment_content ),
            $tone_label
        );

        $agent_slug = $this->settings->get( 'default_agent_slug', 'default' );
        $user_id    = get_current_user_id();

        $response = $this->ai_client->chat(
            $prompt,
            $user_id,
            $agent_slug,
            'suggest-reply-' . $comment_id,
            'comment-suggest'
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $reply = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;

        return rest_ensure_response( array( 'suggestion' => wp_strip_all_tags( $reply ) ) );
    }
}
}
