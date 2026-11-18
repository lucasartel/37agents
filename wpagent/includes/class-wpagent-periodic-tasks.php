<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPAgent_Periodic_Tasks' ) ) {
class WPAgent_Periodic_Tasks {
	const CRON_HOOK = 'wpagent_process_periodic_tasks';

	private $repository;
	private $settings;
	private $agents;
	private $ai_client;

	public function __construct( WPAgent_Repository $repository, WPAgent_Settings $settings, WPAgent_Agents $agents, ?WPAgent_AI_Client $ai_client = null ) {
		$this->repository = $repository;
		$this->settings   = $settings;
		$this->agents     = $agents;
		$this->ai_client  = $ai_client;
	}

	public function register() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
		add_action( 'init', array( $this, 'maybe_process_due_on_request' ), 25 );
		add_action( self::CRON_HOOK, array( $this, 'process_due_tasks' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_post_wpagent_save_periodic_content', array( $this, 'handle_save_content' ) );
			add_action( 'admin_post_wpagent_periodic_content_action', array( $this, 'handle_content_action' ) );
			add_action( 'admin_post_wpagent_run_periodic_content_now', array( $this, 'handle_run_now' ) );
		}
	}

	public static function frequency_options() {
		return array(
			'hourly'     => __( 'A cada hora', 'wpagent' ),
			'twicedaily' => __( 'Duas vezes ao dia', 'wpagent' ),
			'daily'      => __( 'Diariamente', 'wpagent' ),
			'weekly'     => __( 'Semanalmente', 'wpagent' ),
		);
	}

	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['wpagent_five_minutes'] ) ) {
			$schedules['wpagent_five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'A cada 5 minutos (WPAgents)', 'wpagent' ),
			);
		}
		if ( ! isset( $schedules['wpagent_weekly'] ) ) {
			$schedules['wpagent_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Semanalmente', 'wpagent' ),
			);
		}

		return $schedules;
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'wpagent_five_minutes', self::CRON_HOOK );
		}
	}

	public function maybe_process_due_on_request() {
		$last_check = (int) get_transient( 'wpagent_last_periodic_check' );
		if ( ( time() - $last_check ) < 60 ) {
			return;
		}
		set_transient( 'wpagent_last_periodic_check', time(), 120 );

		$items = $this->repository->list_periodic_contents( array( 'status' => 'active' ) );
		if ( empty( $items ) ) {
			return;
		}

		$now = wp_date( 'Y-m-d H:i:s' );
		$has_due = false;
		foreach ( $items as $item ) {
			if ( empty( $item['next_run_at'] ) || $item['next_run_at'] <= $now ) {
				$has_due = true;
				break;
			}
		}

		if ( $has_due ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time(), self::CRON_HOOK );
			}
			if ( function_exists( 'spawn_cron' ) ) {
				spawn_cron();
			}
		}
	}

	public function add_admin_menu() {
		add_submenu_page(
			'wpagent',
			__( 'Conteudo Periodico', 'wpagent' ),
			__( 'Conteudo Periodico', 'wpagent' ),
			'manage_options',
			'wpagent-periodic-tasks',
			array( $this, 'render_admin_page' )
		);
	}

	public function handle_save_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_save_periodic_content', 'wpagent_nonce' );

		$id = absint( $_POST['id'] ?? 0 );
		$data = array(
			'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
			'agent_slug'    => sanitize_key( $_POST['agent_slug'] ?? 'default' ),
			'prompt'        => wp_kses_post( $_POST['prompt'] ?? '' ),
			'frequency'     => sanitize_key( $_POST['frequency'] ?? 'daily' ),
			'post_category' => absint( $_POST['post_category'] ?? 1 ),
			'status'        => sanitize_key( $_POST['status'] ?? 'active' ),
		);

		if ( empty( $data['name'] ) || empty( $data['prompt'] ) ) {
			$url = add_query_arg(
				array(
					'page'  => 'wpagent-periodic-tasks',
					'error' => 'empty_fields',
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $url );
			exit;
		}

		if ( $id > 0 ) {
			$this->repository->update_periodic_content( $id, $data );
			$msg = 'updated';
		} else {
			$data['next_run_at'] = wp_date( 'Y-m-d H:i:s' );
			$this->repository->create_periodic_content( $data );
			$msg = 'created';
			$this->spawn_cron();
		}

		$url = add_query_arg(
			array(
				'page'    => 'wpagent-periodic-tasks',
				'success' => $msg,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_content_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_periodic_content_action', 'wpagent_nonce' );

		$id     = absint( $_GET['id'] ?? 0 );
		$action = sanitize_key( $_GET['do_action'] ?? '' );

		if ( $id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpagent-periodic-tasks' ) );
			exit;
		}

		if ( 'delete' === $action ) {
			$this->repository->delete_periodic_content( $id );
		} elseif ( 'pause' === $action ) {
			$this->repository->update_periodic_content( $id, array( 'status' => 'paused' ) );
		} elseif ( 'activate' === $action ) {
			$next_target = $this->calculate_next_run( 'daily' );
			$this->repository->update_periodic_content(
				$id,
				array(
					'status'      => 'active',
					'next_run_at' => wp_date( 'Y-m-d H:i:s', $next_target ),
				)
			);
			wp_schedule_single_event( $next_target, self::CRON_HOOK );
			$this->spawn_cron();
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'wpagent-periodic-tasks', 'success' => 'action_done' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sem permissao.', 'wpagent' ) );
		}

		check_admin_referer( 'wpagent_run_periodic_content_now' );

		$id = absint( $_GET['id'] ?? 0 );
		$item = $this->repository->get_periodic_content( $id );

		if ( empty( $item ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'wpagent-periodic-tasks', 'error' => 'not_found' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$res = $this->execute_content_task( $item );

		if ( is_wp_error( $res ) ) {
			$url = add_query_arg(
				array(
					'page'  => 'wpagent-periodic-tasks',
					'error' => 'exec_failed',
					'msg'   => urlencode( $res->get_error_message() ),
				),
				admin_url( 'admin.php' )
			);
		} else {
			$url = add_query_arg(
				array(
					'page'    => 'wpagent-periodic-tasks',
					'success' => 'run_ok',
					'post_id' => absint( $res ),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	public function process_due_tasks() {
		$items = $this->repository->list_periodic_contents( array( 'status' => 'active' ) );
		$now   = wp_date( 'Y-m-d H:i:s' );

		foreach ( $items as $item ) {
			if ( empty( $item['next_run_at'] ) || $item['next_run_at'] <= $now ) {
				$this->execute_content_task( $item );
			}
		}
	}

	private function execute_content_task( $item ) {
		$ai_client = $this->get_ai_client();
		if ( ! $ai_client ) {
			return new WP_Error( 'wpagent_no_ai', __( 'Cliente de IA nao disponivel no momento.', 'wpagent' ) );
		}

		$instruction  = trim( $item['prompt'] );
		$instruction .= "\n\nIMPORTANTE: Crie um post para blog do WordPress. A PRIMEIRA LINHA da sua resposta deve ser EXCLUSIVAMENTE o titulo do post, sem prefixos ou aspas. A partir da segunda linha, forneca todo o conteudo em Markdown bem estruturado. Nao insira comentarios ou agradecimentos adicionais.";

		$user_id = get_current_user_id() ?: 1;
		$response = $ai_client->chat(
			$instruction,
			$user_id,
			$item['agent_slug'],
			'periodic-' . $item['id'],
			'periodic'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$reply = is_array( $response ) ? ( $response['reply'] ?? '' ) : (string) $response;
		$lines = explode( "\n", trim( $reply ) );
		$title_line = trim( array_shift( $lines ) );
		$title = ltrim( $title_line, "# \t\"'" );
		if ( empty( $title ) ) {
			$title = $item['name'] ?: __( 'Rascunho WPAgent', 'wpagent' );
		}

		$content = trim( implode( "\n", $lines ) );
		if ( empty( $content ) ) {
			$content = $reply;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'draft',
				'post_title'    => wp_strip_all_tags( $title ),
				'post_content'  => wp_kses_post( $content ),
				'post_category' => array( (int) $item['post_category'] ),
				'post_author'   => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_wpagent_generated_by', array(
			'agent_slug'     => sanitize_key( $item['agent_slug'] ),
			'generated_at'   => current_time( 'mysql' ),
			'prompt_preview' => mb_substr( sanitize_textarea_field( $item['prompt'] ), 0, 200 ),
		) );

		$next_run_ts = $this->calculate_next_run( $item['frequency'] );
		$next_run_date = wp_date( 'Y-m-d H:i:s', $next_run_ts );

		$this->repository->update_periodic_content(
			$item['id'],
			array(
				'last_run_at'  => wp_date( 'Y-m-d H:i:s' ),
				'next_run_at'  => $next_run_date,
				'last_post_id' => absint( $post_id ),
			)
		);

		wp_schedule_single_event( $next_run_ts, self::CRON_HOOK );
		$this->spawn_cron();

		return absint( $post_id );
	}

	private function spawn_cron() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return;
		}

		wp_remote_post(
			site_url( 'wp-cron.php?doing_wp_cron=' . rawurlencode( microtime( true ) ) ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	private function calculate_next_run( $frequency ) {
		$now = time();
		switch ( $frequency ) {
			case 'hourly':
				return $now + HOUR_IN_SECONDS;
			case 'twicedaily':
				return $now + ( 12 * HOUR_IN_SECONDS );
			case 'weekly':
				return $now + WEEK_IN_SECONDS;
			case 'daily':
			default:
				return $now + DAY_IN_SECONDS;
		}
	}

	private function get_ai_client() {
		if ( $this->ai_client ) {
			return $this->ai_client;
		}
		if ( class_exists( 'WPAgent_AI_Client' ) ) {
			$this->ai_client = new WPAgent_AI_Client(
				$this->repository,
				$this->settings,
				$this->agents
			);
			return $this->ai_client;
		}
		return null;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$edit_id = absint( $_GET['edit'] ?? 0 );
		$editing_item = $edit_id > 0 ? $this->repository->get_periodic_content( $edit_id ) : null;
		$items = $this->repository->list_periodic_contents();
		$agents_list = $this->agents->get_agent_options();
		$categories = get_categories( array( 'hide_empty' => false ) );
		$frequencies = self::frequency_options();

		if ( ! empty( $_GET['success'] ) ) {
			$succ = sanitize_key( $_GET['success'] );
			if ( 'run_ok' === $succ ) {
				$pid = absint( $_GET['post_id'] ?? 0 );
				$link = get_edit_post_link( $pid );
				echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( sprintf(
					/* translators: 1: edit post URL, 2: post ID. */
					__( 'Rascunho de conteúdo gerado com sucesso! <a href="%1$s" target="_blank">Clique aqui para revisar o Rascunho #%2$d</a>.', 'wpagent' ),
					esc_url( $link ),
					$pid
				) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Acao realizada com sucesso.', 'wpagent' ) . '</p></div>';
			}
		}

		if ( ! empty( $_GET['error'] ) ) {
			$err = sanitize_key( $_GET['error'] );
			if ( 'exec_failed' === $err ) {
				$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );
				/* translators: %s: error message. */
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'Erro ao executar tarefa de conteudo: %s', 'wpagent' ), $msg ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Erro ao salvar. Verifique se preencheu todos os campos.', 'wpagent' ) . '</p></div>';
			}
		}
		WPAgent_Admin_UI::render_header(
			__( 'Conteúdo Periódico do WPAgents', 'wpagent' ),
			__( 'Crie rotinas automáticas para os agentes gerarem artigos em modo RASCUNHO com aprovação editorial.', 'wpagent' )
		);
		?>
		<div class="wpagents-form-wrap">

			<div id="col-container" class="wp-clearfix">
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo $editing_item ? esc_html__( 'Editar Conteudo Periodico', 'wpagent' ) : esc_html__( 'Novo Conteudo Periodico', 'wpagent' ); ?></h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="wpagent_save_periodic_content">
								<input type="hidden" name="id" value="<?php echo esc_attr( $editing_item['id'] ?? 0 ); ?>">
								<?php wp_nonce_field( 'wpagent_save_periodic_content', 'wpagent_nonce' ); ?>

								<div class="form-field form-required">
									<label for="name"><?php esc_html_e( 'Identificacao do Agendamento', 'wpagent' ); ?></label>
									<input name="name" id="name" type="text" value="<?php echo esc_attr( $editing_item['name'] ?? '' ); ?>" required aria-required="true">
									<p><?php esc_html_e( 'Nome interno para identificar esta rotina de postagem.', 'wpagent' ); ?></p>
								</div>

								<div class="form-field">
									<label for="agent_slug"><?php esc_html_e( 'Agente Autor', 'wpagent' ); ?></label>
									<select name="agent_slug" id="agent_slug">
										<?php foreach ( $agents_list as $slug => $name ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $editing_item['agent_slug'] ?? 'default', $slug ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</select>
									<p><?php esc_html_e( 'O agente selecionado usara seu proprio conhecimento e personalidade para criar o post.', 'wpagent' ); ?></p>
								</div>

								<div class="form-field">
									<label for="post_category"><?php esc_html_e( 'Categoria do Post', 'wpagent' ); ?></label>
									<select name="post_category" id="post_category">
										<?php foreach ( $categories as $cat ) : ?>
											<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $editing_item['post_category'] ?? 1, $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="form-field">
									<label for="frequency"><?php esc_html_e( 'Periodicidade', 'wpagent' ); ?></label>
									<select name="frequency" id="frequency">
										<?php foreach ( $frequencies as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $editing_item['frequency'] ?? 'daily', $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="form-field form-required">
									<label for="prompt"><?php esc_html_e( 'Instrucoes de Conteudo', 'wpagent' ); ?></label>
									<textarea name="prompt" id="prompt" rows="6" required aria-required="true"><?php echo esc_textarea( $editing_item['prompt'] ?? '' ); ?></textarea>
									<p><?php esc_html_e( 'Ex: Escreva um artigo completo sobre tecnicas de SEO para WordPress com exemplos práticos.', 'wpagent' ); ?></p>
								</div>

								<div class="form-field">
									<label for="status"><?php esc_html_e( 'Status', 'wpagent' ); ?></label>
									<select name="status" id="status">
										<option value="active" <?php selected( $editing_item['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Ativo', 'wpagent' ); ?></option>
										<option value="paused" <?php selected( $editing_item['status'] ?? 'active', 'paused' ); ?>><?php esc_html_e( 'Pausado', 'wpagent' ); ?></option>
									</select>
								</div>

								<?php submit_button( $editing_item ? __( 'Salvar Alteracoes', 'wpagent' ) : __( 'Adicionar Conteudo Periodico', 'wpagent' ) ); ?>
								<?php if ( $editing_item ) : ?>
									<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-periodic-tasks' ) ); ?>" class="button"><?php esc_html_e( 'Cancelar Edicao', 'wpagent' ); ?></a></p>
								<?php endif; ?>
							</form>
						</div>
					</div>
				</div>

				<div id="col-right">
					<div class="col-wrap">
						<table class="widefat striped wp-list-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Nome / Agente', 'wpagent' ); ?></th>
									<th><?php esc_html_e( 'Categoria', 'wpagent' ); ?></th>
									<th><?php esc_html_e( 'Frequencia', 'wpagent' ); ?></th>
									<th><?php esc_html_e( 'Status', 'wpagent' ); ?></th>
									<th><?php esc_html_e( 'Ultimo Rascunho Criado', 'wpagent' ); ?></th>
									<th><?php esc_html_e( 'Acoes', 'wpagent' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $items ) ) : ?>
									<tr>
										<td colspan="6"><?php esc_html_e( 'Nenhum conteudo periodico configurado ainda.', 'wpagent' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $items as $row ) : ?>
										<?php
										$cat_obj = get_category( absint( $row['post_category'] ) );
										$cat_name = $cat_obj && ! is_wp_error( $cat_obj ) ? $cat_obj->name : __( 'Sem categoria', 'wpagent' );
										?>
										<tr>
											<td>
												<strong><?php echo esc_html( $row['name'] ); ?></strong><br>
												<span class="description"><?php echo esc_html__( 'Agente:', 'wpagent' ) . ' <strong>' . esc_html( $agents_list[ $row['agent_slug'] ] ?? $row['agent_slug'] ) . '</strong>'; ?></span>
											</td>
											<td><?php echo esc_html( $cat_name ); ?></td>
											<td><?php echo esc_html( $frequencies[ $row['frequency'] ] ?? $row['frequency'] ); ?></td>
											<td>
												<?php if ( 'active' === $row['status'] ) : ?>
													<span class="wpagent-badge" style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:12px;"><?php esc_html_e( 'Ativo', 'wpagent' ); ?></span>
												<?php else : ?>
													<span class="wpagent-badge" style="background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:12px;"><?php esc_html_e( 'Pausado', 'wpagent' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( ! empty( $row['last_post_id'] ) && $row['last_post_id'] > 0 ) : ?>
													<a href="<?php echo esc_url( get_edit_post_link( absint( $row['last_post_id'] ) ) ); ?>" class="button button-small" target="_blank">
														<?php
														printf(
															/* translators: %d: post ID */
															esc_html__( 'Revisar Rascunho #%d', 'wpagent' ),
															absint( $row['last_post_id'] )
														);
														?>
													</a>
													<?php if ( ! empty( $row['last_run_at'] ) ) : ?>
														<br><span class="description" style="font-size:11px;"><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $row['last_run_at'] ) ) ); ?></span>
													<?php endif; ?>
												<?php else : ?>
													<span class="description"><?php esc_html_e( 'Nenhum rascunho gerado', 'wpagent' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_run_periodic_content_now&id=' . $row['id'] ), 'wpagent_run_periodic_content_now' ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Executar Agora', 'wpagent' ); ?></a>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpagent-periodic-tasks&edit=' . $row['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'wpagent' ); ?></a>
												<?php if ( 'active' === $row['status'] ) : ?>
													<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_periodic_content_action&do_action=pause&id=' . $row['id'] ), 'wpagent_periodic_content_action', 'wpagent_nonce' ) ); ?>" class="button button-small"><?php esc_html_e( 'Pausar', 'wpagent' ); ?></a>
												<?php else : ?>
													<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_periodic_content_action&do_action=activate&id=' . $row['id'] ), 'wpagent_periodic_content_action', 'wpagent_nonce' ) ); ?>" class="button button-small"><?php esc_html_e( 'Ativar', 'wpagent' ); ?></a>
												<?php endif; ?>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpagent_periodic_content_action&do_action=delete&id=' . $row['id'] ), 'wpagent_periodic_content_action', 'wpagent_nonce' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Tem certeza que deseja excluir esta rotina?', 'wpagent' ); ?>');" style="color:#b32d2e;"><?php esc_html_e( 'Excluir', 'wpagent' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
		WPAgent_Admin_UI::render_footer();
	}
}
}
