<?php
/**
 * Settings page and option handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings {

	const OPTION = 'aips_settings';
	const GROUP  = 'aips_settings_group';
	const PAGE   = 'aips-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aips_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AIPS_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function defaults() {
		return array(
			'api_base'       => 'https://api.deepseek.com',
			'api_key'        => '',
			'model'          => 'deepseek-flash',
			'summary_length' => 120,
			'slug_length'    => 60,
			'separator'      => '-',
		);
	}

	public static function activate() {
		$existing = get_option( self::OPTION, null );
		if ( ! is_array( $existing ) ) {
			add_option( self::OPTION, self::defaults() );
			return;
		}

		update_option( self::OPTION, array_merge( self::defaults(), $existing ) );
	}

	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_merge( self::defaults(), $stored );

		$settings['api_base']       = untrailingslashit( trim( (string) $settings['api_base'] ) );
		$settings['api_key']        = trim( (string) $settings['api_key'] );
		$settings['model']          = trim( (string) $settings['model'] );
		$settings['summary_length'] = max( 40, min( 300, (int) $settings['summary_length'] ) );
		$settings['slug_length']    = max( 20, min( 120, (int) $settings['slug_length'] ) );
		$settings['separator']      = in_array( $settings['separator'], array( '-', '_' ), true ) ? $settings['separator'] : '-';

		if ( '' === $settings['model'] ) {
			$defaults         = self::defaults();
			$settings['model'] = $defaults['model'];
		}

		return $settings;
	}

	public static function add_menu() {
		add_options_page(
			'AI 摘要与拼音 Slug',
			'AI 摘要与拼音 Slug',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'aips_api',
			'API 配置',
			array( __CLASS__, 'section_api' ),
			self::PAGE
		);

		add_settings_field(
			'aips_api_base',
			'API 地址',
			array( __CLASS__, 'field_api_base' ),
			self::PAGE,
			'aips_api',
			array( 'label_for' => 'aips_api_base' )
		);

		add_settings_field(
			'aips_api_key',
			'API Key',
			array( __CLASS__, 'field_api_key' ),
			self::PAGE,
			'aips_api',
			array( 'label_for' => 'aips_api_key' )
		);

		add_settings_field(
			'aips_model',
			'模型',
			array( __CLASS__, 'field_model' ),
			self::PAGE,
			'aips_api',
			array( 'label_for' => 'aips_model' )
		);

		add_settings_section(
			'aips_rules',
			'生成规则',
			array( __CLASS__, 'section_rules' ),
			self::PAGE
		);

		add_settings_field(
			'aips_summary_length',
			'摘要长度',
			array( __CLASS__, 'field_summary_length' ),
			self::PAGE,
			'aips_rules',
			array( 'label_for' => 'aips_summary_length' )
		);

		add_settings_field(
			'aips_slug_length',
			'Slug 最大长度',
			array( __CLASS__, 'field_slug_length' ),
			self::PAGE,
			'aips_rules',
			array( 'label_for' => 'aips_slug_length' )
		);

		add_settings_field(
			'aips_separator',
			'Slug 分隔符',
			array( __CLASS__, 'field_separator' ),
			self::PAGE,
			'aips_rules',
			array( 'label_for' => 'aips_separator' )
		);
	}

	public static function section_api() {
		echo '<p>填写任意 OpenAI 兼容服务的地址与密钥。接口地址通常以 <code>/v1</code> 结尾；如果已经填写完整的 <code>/chat/completions</code> 地址，插件会直接使用。</p>';
		echo '<p>API Key 以明文保存在 WordPress 的 options 表中，请保护好数据库和后台管理员权限。不需要鉴权的本地模型可以留空。</p>';
		echo '<p>生成时会把文章标题和正文发送到该接口，请确认服务商的数据与隐私政策。</p>';
	}

	public static function section_rules() {
		echo '<p>Google 建议在 URL 中使用短横线分隔单词，并将 URL 控制在约 60 个字符以内。新闻与资讯站点常见的 slug 为 3–5 个关键词。默认值按此设置，可按站点习惯调整。</p>';
		echo '<p><a href="https://developers.google.com/search/docs/crawling-indexing/url-structure" target="_blank" rel="noopener noreferrer">Google 官方：网址结构最佳实践</a></p>';
	}

	public static function field_api_base() {
		$settings = self::get();
		printf(
			'<input type="url" class="regular-text code" id="aips_api_base" name="%1$s[api_base]" value="%2$s" placeholder="https://api.openai.com/v1" />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['api_base'] )
		);
		echo '<p class="description">示例：<code>https://api.openai.com/v1</code>、<code>https://api.deepseek.com/v1</code>、<code>http://localhost:11434/v1</code>（Ollama）、<code>http://localhost:1234/v1</code>（LM Studio）。</p>';
	}

	public static function field_api_key() {
		$settings = self::get();
		$has_key  = '' !== $settings['api_key'];
		$hint     = $has_key
			? '已保存：<code>' . esc_html( self::mask_key( $settings['api_key'] ) ) . '</code>。留空则保持不变。'
			: '尚未保存 Key。若服务不需要鉴权（例如本地 Ollama），可留空。';

		printf(
			'<input type="password" class="regular-text code" id="aips_api_key" name="%1$s[api_key]" value="" autocomplete="new-password" placeholder="%2$s" />',
			esc_attr( self::OPTION ),
			esc_attr( $has_key ? '已保存，留空不修改' : 'sk-...' )
		);
		echo '<p class="description">' . wp_kses_post( $hint ) . '</p>';
		printf(
			'<p><label><input type="checkbox" name="%1$s[clear_key]" value="1" /> 清除已保存的 Key（保存后生效）</label></p>',
			esc_attr( self::OPTION )
		);
	}

	public static function field_model() {
		$settings = self::get();
		printf(
			'<input type="text" class="regular-text code" id="aips_model" name="%1$s[model]" value="%2$s" list="aips-model-options" />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['model'] )
		);
		echo '<datalist id="aips-model-options">';
		foreach ( array( 'gpt-4o-mini', 'gpt-4.1-mini', 'deepseek-chat', 'qwen-plus', 'glm-4-flash' ) as $model ) {
			echo '<option value="' . esc_attr( $model ) . '"></option>';
		}
		echo '</datalist>';
		echo '<p class="description">填写服务商提供的模型名称，例如 <code>gpt-4o-mini</code>、<code>deepseek-chat</code>。</p>';
	}

	public static function field_summary_length() {
		$settings = self::get();
		printf(
			'<input type="number" class="small-text" id="aips_summary_length" name="%1$s[summary_length]" value="%2$d" min="40" max="300" step="10" /> 字',
			esc_attr( self::OPTION ),
			(int) $settings['summary_length']
		);
		echo '<p class="description">作为提示词中的目标长度，模型可能略有出入。</p>';
	}

	public static function field_slug_length() {
		$settings = self::get();
		printf(
			'<input type="number" class="small-text" id="aips_slug_length" name="%1$s[slug_length]" value="%2$d" min="20" max="120" step="5" /> 字符',
			esc_attr( self::OPTION ),
			(int) $settings['slug_length']
		);
		echo '<p class="description">含分隔符的硬上限，超出时会在单词边界截断。主流做法是控制在 60 字符以内。</p>';
	}

	public static function field_separator() {
		$settings = self::get();
		echo '<select id="aips_separator" name="' . esc_attr( self::OPTION ) . '[separator]">';
		echo '<option value="-" ' . selected( $settings['separator'], '-', false ) . '>短横线 -（推荐）</option>';
		echo '<option value="_" ' . selected( $settings['separator'], '_', false ) . '>下划线 _</option>';
		echo '</select>';
		echo '<p class="description">Google 明确建议使用短横线，不推荐下划线。</p>';
	}

	public static function sanitize( $input ) {
		$old = self::get();
		$out = $old;

		$input = is_array( $input ) ? $input : array();

		$base = isset( $input['api_base'] ) && is_string( $input['api_base'] ) ? trim( $input['api_base'] ) : '';
		if ( '' === $base ) {
			add_settings_error( self::OPTION, 'aips_api_base', 'API 地址不能为空，已保留原值。', 'error' );
		} else {
			$url    = esc_url_raw( $base );
			$host   = wp_parse_url( $url, PHP_URL_HOST );
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

			if ( ! $url || ! $host || ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
				add_settings_error( self::OPTION, 'aips_api_base', 'API 地址无效，请填写以 http:// 或 https:// 开头的完整地址。已保留原值。', 'error' );
			} else {
				$out['api_base'] = untrailingslashit( $url );
			}
		}

		if ( ! empty( $input['clear_key'] ) ) {
			$out['api_key'] = '';
		} elseif ( isset( $input['api_key'] ) && is_string( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( trim( $input['api_key'] ) );
		}

		$model = isset( $input['model'] ) && is_string( $input['model'] ) ? sanitize_text_field( trim( $input['model'] ) ) : '';
		if ( '' !== $model ) {
			$out['model'] = $model;
		}

		if ( isset( $input['summary_length'] ) ) {
			$out['summary_length'] = max( 40, min( 300, (int) $input['summary_length'] ) );
		}

		if ( isset( $input['slug_length'] ) ) {
			$out['slug_length'] = max( 20, min( 120, (int) $input['slug_length'] ) );
		}

		if ( isset( $input['separator'] ) && in_array( $input['separator'], array( '-', '_' ), true ) ) {
			$out['separator'] = $input['separator'];
		}

		return $out;
	}

	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'aips-settings',
			AIPS_PLUGIN_URL . 'assets/settings.js',
			array( 'jquery' ),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-settings',
			'AIPS_SETTINGS',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aips_settings' ),
				'action'  => 'aips_test_connection',
			)
		);
	}

	public static function ajax_test_connection() {
		check_ajax_referer( 'aips_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足。' ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 );
		}

		$saved = self::get();

		$base = isset( $_POST['api_base'] ) && is_string( $_POST['api_base'] ) ? trim( wp_unslash( $_POST['api_base'] ) ) : '';
		if ( '' === $base ) {
			$base = $saved['api_base'];
		}

		$key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] ) ? trim( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( '' === $key ) {
			$key = $saved['api_key'];
		}

		$model = isset( $_POST['model'] ) && is_string( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		if ( '' === $model ) {
			$model = $saved['model'];
		}

		$url    = esc_url_raw( $base );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! $url || ! $host || ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
			wp_send_json_error( array( 'message' => 'API 地址无效，请填写以 http:// 或 https:// 开头的完整地址。' ), 400 );
		}

		$start  = microtime( true );
		$result = AIPS_API::test( $url, $key, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					'连接成功：模型 %s 可用，耗时 %s 秒。',
					$model,
					number_format_i18n( microtime( true ) - $start, 2 )
				),
			)
		);
	}

	public static function action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">设置</a>';
		return $links;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>AI 摘要与拼音 Slug</h1>
			<p>在文章编辑页点击「生成摘要与拼音 Slug」，插件会使用编辑器中的当前内容调用所配置的接口：摘要填入摘要栏，拼音 slug 填入固定链接。保存文章后生效。</p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				?>
				<p class="submit">
					<?php submit_button( '保存设置', 'primary', 'submit', false ); ?>
					<button type="button" class="button" id="aips-test-connection">测试连接</button>
					<span class="spinner" id="aips-test-spinner"></span>
				</p>
				<p class="description">测试使用当前表单值（无需先保存）；Key 留空时使用已保存的 Key。</p>
				<div id="aips-test-result" class="notice inline" style="display:none;"></div>
			</form>
		</div>
		<?php
	}

	private static function mask_key( $key ) {
		$key = (string) $key;
		if ( strlen( $key ) <= 8 ) {
			return '已保存';
		}
		return substr( $key, 0, 3 ) . '…' . substr( $key, -4 );
	}
}
