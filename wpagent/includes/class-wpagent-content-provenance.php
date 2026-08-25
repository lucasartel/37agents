<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPAgent_Content_Provenance' ) ) {
class WPAgent_Content_Provenance {

    public function register() {
        add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
        add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
        add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
    }

    public function add_column( $columns ) {
        $columns['wpagent_generated'] = __( 'Gerado por IA', 'wpagent' );
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( 'wpagent_generated' !== $column ) {
            return;
        }
        $provenance = get_post_meta( $post_id, '_wpagent_generated_by', true );
        if ( empty( $provenance ) ) {
            echo '—';
            return;
        }
        printf(
            '<span title="%s" style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:11px;">%s</span>',
            esc_attr( sprintf(
                /* translators: 1: agent slug, 2: date */
                __( 'Agente: %1$s | Gerado em: %2$s', 'wpagent' ),
                $provenance['agent_slug'] ?? '',
                $provenance['generated_at'] ?? ''
            ) ),
            esc_html__( '✦ WPAgents IA', 'wpagent' )
        );
    }

    public function add_meta_box() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( ! $post_id ) {
            return;
        }
        $provenance = get_post_meta( $post_id, '_wpagent_generated_by', true );
        if ( empty( $provenance ) ) {
            return;
        }
        add_meta_box(
            'wpagent-content-provenance',
            __( 'Gerado por WPAgents IA', 'wpagent' ),
            array( $this, 'render_meta_box' ),
            array( 'post', 'page' ),
            'side',
            'low'
        );
    }

    public function render_meta_box( $post ) {
        $provenance = get_post_meta( $post->ID, '_wpagent_generated_by', true );
        if ( empty( $provenance ) ) {
            return;
        }
        echo '<table class="widefat" style="font-size:12px;">';
        echo '<tr><th>' . esc_html__( 'Agente', 'wpagent' ) . '</th><td>' . esc_html( $provenance['agent_slug'] ?? '—' ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Gerado em', 'wpagent' ) . '</th><td>' . esc_html( $provenance['generated_at'] ?? '—' ) . '</td></tr>';
        if ( ! empty( $provenance['prompt_preview'] ) ) {
            echo '<tr><th>' . esc_html__( 'Prompt (prévia)', 'wpagent' ) . '</th><td><em>' . esc_html( $provenance['prompt_preview'] ) . '</em></td></tr>';
        }
        echo '</table>';
    }
}
}
