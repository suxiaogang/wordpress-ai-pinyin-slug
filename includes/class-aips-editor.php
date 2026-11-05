<?php
/**
 * Post editor integration: meta box, assets, and generation endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Editor {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aips_generate', array( __CLASS__, 'ajax_generate' ) );
	}

	public static function get_post_types() {
		$types = apply_filters( 'aips_post_types', array( 'post' ) );
		$types = is_array( $types ) ? $types : array( 'post' );
		$types = array_map( 'sanitize_key', $types );

		return array_values( array_unique( array_filter( $types ) ) );
	}

	public static function add_meta_box( $post_type ) {
		if ( ! in_array( $post_type, self::get_post_types(), true ) ) {
			return;
		}

		add_meta_box(
			'aips_box',
			'AI 摘要与拼音 Slug',
			array( __CLASS__, 'render_meta_box' ),
			$post_type,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		$settings   = AIPS_Settings::get();
		$base_ready = '' !== trim( $settings['api_base'] );
		$key_ready  = '' !== $settings['api_key'];
		?>
		<div class="aips-box">
			<?php if ( ! $base_ready ) : ?>
				<div class="notice notice-error inline">
					<p>尚未配置 API 地址，请先到 <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . AIPS_Settings::PAGE ) ); ?>">设置页</a> 配置。</p>
				</div>
			<?php endif; ?>

			<p class="aips-intro">使用编辑器中的当前标题和正文（包含未保存修改）生成摘要与拼音 slug。摘要会填入「摘要」栏，slug 会填入「固定链接」，保存文章后生效。</p>

			<p class="aips-actions">
				<button type="button" class="button button-primary" id="aips-generate" <?php disabled( ! $base_ready ); ?>>生成摘要与拼音 Slug</button>
				<span class="spinner" id="aips-spinner"></span>
			</p>

			<div id="aips-status" class="aips-status" role="status" aria-live="polite" hidden></div>

			<div id="aips-result" class="aips-result" hidden>
				<p><strong>摘要</strong></p>
				<textarea id="aips-summary" class="widefat" rows="4" readonly></textarea>
				<p><strong>拼音 Slug</strong></p>
				<p><code id="aips-slug" class="aips-slug"></code></p>
			</div>

			<p class="description">
				当前模型：<code><?php echo esc_html( $settings['model'] ); ?></code>；
				摘要约 <?php echo (int) $settings['summary_length']; ?> 字；
				Slug 最长 <?php echo (int) $settings['slug_length']; ?> 字符，分隔符 <code><?php echo esc_html( $settings['separator'] ); ?></code>。
				<?php if ( ! $key_ready ) : ?>
					（未填写 API Key；若服务不需要 Key 可忽略）
				<?php endif; ?>
			</p>
			<p class="description">若文章已有固定链接，生成后会覆盖编辑器中的 slug（保存前不会写入数据库）。</p>
		</div>
		<?php
	}

	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::get_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'aips-editor',
			AIPS_PLUGIN_URL . 'assets/editor.css',
			array(),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-editor',
			AIPS_PLUGIN_URL . 'assets/editor.js',
			array( 'jquery' ),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-editor',
			'AIPS_EDITOR',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aips_editor' ),
				'action'   => 'aips_generate',
				'messages' => array(
					'button'             => '生成摘要与拼音 Slug',
					'working'            => '正在生成，请稍候…',
					'empty'              => '请先填写文章标题或正文。',
					'noPostId'           => '未获取到文章 ID，请刷新页面后重试。',
					'requestFailed'      => '请求失败，请检查网络或接口配置。',
					'unknown'            => '生成失败，请重试。',
					'applied'            => '已填入摘要与拼音 Slug，保存文章后生效。',
					'appliedSummaryOnly' => '已填入摘要。',
					'appliedSlugOnly'    => '已填入拼音 Slug。',
					'appliedNoFields'    => '摘要和拼音 Slug 已生成；当前页面未找到对应输入框，请从上方复制。',
					'appliedNoExcerpt'   => '摘要已生成；当前页面没有摘要输入框，请从上方复制摘要。',
					'appliedNoSlug'      => '拼音 Slug 已生成；未找到 slug 输入框，请从上方复制拼音 Slug。',
				),
			)
		);
	}

	public static function ajax_generate() {
		check_ajax_referer( 'aips_editor', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => '无权编辑该文章。' ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::get_post_types(), true ) ) {
			wp_send_json_error( array( 'message' => '文章不存在或该类型不受支持。' ), 404 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 );
		}

		$title = isset( $_POST['title'] ) && is_string( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '';
		$title = trim( html_entity_decode( sanitize_text_field( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $title ) {
			$title = sanitize_text_field( get_the_title( $post ) );
		}

		$content = isset( $_POST['content'] ) && is_string( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		if ( '' === trim( $content ) ) {
			$content = $post->post_content;
		}
		$content = self::normalize_content( $content );

		if ( '' === $title && '' === $content ) {
			wp_send_json_error( array( 'message' => '文章标题和正文为空，无法生成。' ), 400 );
		}

		$settings = AIPS_Settings::get();
		if ( '' === trim( $settings['api_base'] ) ) {
			wp_send_json_error( array( 'message' => '请先在「设置 → AI 摘要与拼音 Slug」中配置 API 地址。' ), 400 );
		}

		$result = AIPS_API::generate( $title, $content, $settings );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}

		wp_send_json_success( $result );
	}

	private static function normalize_content( $content ) {
		$content = (string) $content;
		$content = preg_replace( '/<!--.*?-->/s', '', $content );
		$content = strip_shortcodes( (string) $content );
		$content = html_entity_decode( (string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = wp_strip_all_tags( (string) $content );
		$content = preg_replace( '/\s+/u', ' ', (string) $content );

		return trim( (string) $content );
	}
}
