<?php
/**
 * Event capture.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress and WooCommerce hooks.
 */
class Events {
	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Logger   $logger   Logger.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Logger $logger, Settings $settings ) {
		$this->logger   = $logger;
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_login', array( $this, 'login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'logout' ) );
		add_action( 'wp_login_failed', array( $this, 'login_failed' ) );
		add_action( 'profile_update', array( $this, 'profile_update' ), 10, 3 );
		add_action( 'after_password_reset', array( $this, 'password_reset' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'role_changed' ), 10, 3 );
		add_action( 'user_register', array( $this, 'user_created' ) );
		add_action( 'delete_user', array( $this, 'user_deleted' ) );

		add_action( 'wp_insert_post', array( $this, 'post_saved' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'post_trashed' ) );
		add_action( 'before_delete_post', array( $this, 'post_deleted' ) );
		add_action( 'add_attachment', array( $this, 'media_uploaded' ) );
		add_action( 'delete_attachment', array( $this, 'media_deleted' ) );
		add_action( 'wp_insert_comment', array( $this, 'comment_created' ), 10, 2 );
		add_action( 'transition_comment_status', array( $this, 'comment_status' ), 10, 3 );
		add_action( 'delete_comment', array( $this, 'comment_deleted' ) );

		add_action( 'activated_plugin', array( $this, 'plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'plugin_deactivated' ), 10, 2 );
		add_action( 'deleted_plugin', array( $this, 'plugin_deleted' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'upgrader_complete' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'theme_switched' ), 10, 3 );
		add_action( 'added_option', array( $this, 'option_added' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'option_updated' ), 10, 3 );
		add_action( 'tracevault_setting_updated', array( $this, 'tracevault_setting_updated' ), 10, 3 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_new_order', array( $this, 'woocommerce_order_created' ) );
			add_action( 'woocommerce_update_order', array( $this, 'woocommerce_order_updated' ) );
			add_action( 'woocommerce_order_status_changed', array( $this, 'woocommerce_order_status_changed' ), 10, 4 );
			add_action( 'save_post_product', array( $this, 'woocommerce_product_saved' ), 10, 3 );
			add_action( 'save_post_shop_coupon', array( $this, 'woocommerce_coupon_saved' ), 10, 3 );
		}
	}

	/**
	 * Logs successful login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User.
	 * @return void
	 */
	public function login( $user_login, $user ) {
		$this->logger->log(
			'user.login',
				array(
					'user'      => $user,
					/* translators: %s: user login. */
					'message'   => sprintf( __( 'User "%s" logged in.', 'tracevault-audit-log' ), $user_login ),
				'severity'  => Logger::SEVERITY_INFO,
				'object_type' => 'user',
				'object_id' => $user->ID,
			)
		);
	}

	/**
	 * Logs logout.
	 *
	 * @return void
	 */
	public function logout() {
		$user = wp_get_current_user();
		$this->logger->log(
			'user.logout',
			array(
				'user'        => $user,
				'object_type' => 'user',
				'object_id'   => $user instanceof \WP_User ? $user->ID : 0,
				'message'     => __( 'User logged out.', 'tracevault-audit-log' ),
			)
		);
	}

	/**
	 * Logs failed login.
	 *
	 * @param string $username Username.
	 * @return void
	 */
	public function login_failed( $username ) {
		$this->logger->log(
			'user.login_failed',
				array(
					'username' => sanitize_user( $username ),
					'severity' => Logger::SEVERITY_WARNING,
					'object_type' => 'user',
					/* translators: %s: attempted username. */
					'message'  => sprintf( __( 'Failed login attempt for "%s".', 'tracevault-audit-log' ), sanitize_user( $username ) ),
			)
		);
	}

	/**
	 * Logs profile updates.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Previous user.
	 * @param array    $userdata      New data.
	 * @return void
	 */
	public function profile_update( $user_id, $old_user_data, $userdata = array() ) {
		unset( $userdata['user_pass'] );
		$this->logger->log(
			'user.profile_update',
				array(
					'object_type' => 'user',
					'object_id'   => $user_id,
					/* translators: %d: user ID. */
					'message'     => sprintf( __( 'Profile updated for user ID %d.', 'tracevault-audit-log' ), $user_id ),
				'meta'        => array(
					'user_login' => $old_user_data instanceof \WP_User ? $old_user_data->user_login : '',
				),
			)
		);
	}

	/**
	 * Logs password changes.
	 *
	 * @param \WP_User $user     User.
	 * @param string   $new_pass New password, intentionally ignored.
	 * @return void
	 */
	public function password_reset( $user, $new_pass ) {
		unset( $new_pass );
		$this->logger->log(
			'user.password_change',
			array(
				'user_id'     => $user->ID,
				'username'    => $user->user_login,
					'object_type' => 'user',
					'object_id'   => $user->ID,
					'severity'    => Logger::SEVERITY_NOTICE,
					/* translators: %s: user login. */
					'message'     => sprintf( __( 'Password changed for "%s".', 'tracevault-audit-log' ), $user->user_login ),
			)
		);
	}

	/**
	 * Logs role changes.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $role      New role.
	 * @param array  $old_roles Old roles.
	 * @return void
	 */
	public function role_changed( $user_id, $role, $old_roles ) {
		$this->logger->log(
			'user.role_change',
			array(
					'object_type' => 'user',
					'object_id'   => $user_id,
					'severity'    => Logger::SEVERITY_NOTICE,
					/* translators: 1: user ID, 2: new role. */
					'message'     => sprintf( __( 'Role changed for user ID %1$d to %2$s.', 'tracevault-audit-log' ), $user_id, $role ),
				'meta'        => array(
					'old_roles' => implode( ',', array_map( 'sanitize_key', (array) $old_roles ) ),
					'new_role'  => sanitize_key( $role ),
				),
			)
		);
	}

	/**
	 * Logs user creation.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function user_created( $user_id ) {
		$user = get_userdata( $user_id );
		$this->logger->log(
			'user.create',
			array(
					'user_id'     => $user_id,
					'username'    => $user ? $user->user_login : '',
					'object_type' => 'user',
					'object_id'   => $user_id,
					/* translators: %d: user ID. */
					'message'     => sprintf( __( 'User ID %d was created.', 'tracevault-audit-log' ), $user_id ),
			)
		);
	}

	/**
	 * Logs user deletion.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function user_deleted( $user_id ) {
		$this->logger->log(
			'user.delete',
			array(
					'object_type' => 'user',
					'object_id'   => $user_id,
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: %d: user ID. */
					'message'     => sprintf( __( 'User ID %d was deleted.', 'tracevault-audit-log' ), $user_id ),
			)
		);
	}

	/**
	 * Logs post/page creates and updates.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Whether update.
	 * @return void
	 */
	public function post_saved( $post_id, $post, $update ) {
		if ( ! $post instanceof \WP_Post || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$this->logger->log(
			$update ? 'content.update' : 'content.create',
			array(
					'object_type' => $post->post_type,
					'object_id'   => $post_id,
					/* translators: 1: post type, 2: post title, 3: action label. */
					'message'     => sprintf( __( '%1$s "%2$s" was %3$s.', 'tracevault-audit-log' ), ucfirst( $post->post_type ), $post->post_title, $update ? __( 'updated', 'tracevault-audit-log' ) : __( 'created', 'tracevault-audit-log' ) ),
				'meta'        => array(
					'post_status' => $post->post_status,
				),
			)
		);
	}

	/**
	 * Logs post deletion.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function post_deleted( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$this->logger->log(
			'content.delete',
			array(
					'object_type' => $post->post_type,
					'object_id'   => $post_id,
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: 1: post type, 2: post title. */
					'message'     => sprintf( __( '%1$s "%2$s" was deleted.', 'tracevault-audit-log' ), ucfirst( $post->post_type ), $post->post_title ),
			)
		);
	}

	/**
	 * Logs post trashing as a content delete event.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function post_trashed( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$this->logger->log(
			'content.delete',
			array(
					'object_type' => $post->post_type,
					'object_id'   => $post_id,
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: 1: post type, 2: post title. */
					'message'     => sprintf( __( '%1$s "%2$s" was moved to trash.', 'tracevault-audit-log' ), ucfirst( $post->post_type ), $post->post_title ),
			)
		);
	}

	/**
	 * Logs media upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function media_uploaded( $attachment_id ) {
		$this->logger->log(
			'media.upload',
				array(
					'object_type' => 'attachment',
					'object_id'   => $attachment_id,
					/* translators: %d: attachment ID. */
					'message'     => sprintf( __( 'Media attachment ID %d was uploaded.', 'tracevault-audit-log' ), $attachment_id ),
			)
		);
	}

	/**
	 * Logs media deletion.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function media_deleted( $attachment_id ) {
		$this->logger->log(
			'media.delete',
			array(
					'object_type' => 'attachment',
					'object_id'   => $attachment_id,
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: %d: attachment ID. */
					'message'     => sprintf( __( 'Media attachment ID %d was deleted.', 'tracevault-audit-log' ), $attachment_id ),
			)
		);
	}

	/**
	 * Logs comment creation.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment    Comment.
	 * @return void
	 */
	public function comment_created( $comment_id, $comment ) {
		$this->logger->log(
			'comment.create',
				array(
					'object_type' => 'comment',
					'object_id'   => $comment_id,
					/* translators: %d: comment ID. */
					'message'     => sprintf( __( 'Comment ID %d was created.', 'tracevault-audit-log' ), $comment_id ),
				'meta'        => array(
					'post_id' => $comment instanceof \WP_Comment ? $comment->comment_post_ID : 0,
				),
			)
		);
	}

	/**
	 * Logs comment status changes.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment.
	 * @return void
	 */
	public function comment_status( $new_status, $old_status, $comment ) {
		if ( $new_status === $old_status ) {
			return;
		}

		$this->logger->log(
			'comment.status_change',
				array(
					'object_type' => 'comment',
					'object_id'   => $comment->comment_ID,
					/* translators: 1: comment ID, 2: old comment status, 3: new comment status. */
					'message'     => sprintf( __( 'Comment ID %1$d changed from %2$s to %3$s.', 'tracevault-audit-log' ), $comment->comment_ID, $old_status, $new_status ),
			)
		);
	}

	/**
	 * Logs comment deletion.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function comment_deleted( $comment_id ) {
		$this->logger->log(
			'comment.delete',
			array(
					'object_type' => 'comment',
					'object_id'   => $comment_id,
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: %d: comment ID. */
					'message'     => sprintf( __( 'Comment ID %d was deleted.', 'tracevault-audit-log' ), $comment_id ),
			)
		);
	}

	/**
	 * Logs plugin activation.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $network_wide Network-wide.
	 * @return void
	 */
	public function plugin_activated( $plugin, $network_wide ) {
		$this->logger->log(
			'system.plugin_activate',
				array(
					'object_type' => 'plugin',
					/* translators: %s: plugin basename. */
					'message'     => sprintf( __( 'Plugin "%s" was activated.', 'tracevault-audit-log' ), $plugin ),
				'meta'        => array( 'network_wide' => (bool) $network_wide ),
			)
		);
	}

	/**
	 * Logs plugin deactivation.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $network_wide Network-wide.
	 * @return void
	 */
	public function plugin_deactivated( $plugin, $network_wide ) {
		$this->logger->log(
			'system.plugin_deactivate',
			array(
					'object_type' => 'plugin',
					'severity'    => Logger::SEVERITY_NOTICE,
					/* translators: %s: plugin basename. */
					'message'     => sprintf( __( 'Plugin "%s" was deactivated.', 'tracevault-audit-log' ), $plugin ),
				'meta'        => array( 'network_wide' => (bool) $network_wide ),
			)
		);
	}

	/**
	 * Logs plugin deletion.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $deleted Whether deletion succeeded.
	 * @return void
	 */
	public function plugin_deleted( $plugin, $deleted ) {
		if ( ! $deleted ) {
			return;
		}

		$this->logger->log(
			'system.plugin_delete',
			array(
					'object_type' => 'plugin',
					'severity'    => Logger::SEVERITY_WARNING,
					/* translators: %s: plugin basename. */
					'message'     => sprintf( __( 'Plugin "%s" was deleted.', 'tracevault-audit-log' ), $plugin ),
			)
		);
	}

	/**
	 * Logs plugin/theme installs and updates.
	 *
	 * @param \WP_Upgrader $upgrader   Upgrader.
	 * @param array        $hook_extra Extra data.
	 * @return void
	 */
	public function upgrader_complete( $upgrader, $hook_extra ) {
		unset( $upgrader );

		$type   = isset( $hook_extra['type'] ) ? sanitize_key( $hook_extra['type'] ) : 'system';
		$action = isset( $hook_extra['action'] ) ? sanitize_key( $hook_extra['action'] ) : 'update';

		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) || ! in_array( $action, array( 'install', 'update' ), true ) ) {
			return;
		}

		$this->logger->log(
			'system.' . $type . '_' . $action,
			array(
					'object_type' => $type,
					'severity'    => Logger::SEVERITY_NOTICE,
					/* translators: 1: object type, 2: action. */
					'message'     => sprintf( __( '%1$s %2$s completed.', 'tracevault-audit-log' ), ucfirst( $type ), $action ),
				'meta'        => array(
					'bulk' => ! empty( $hook_extra['bulk'] ),
				),
			)
		);
	}

	/**
	 * Logs theme switch.
	 *
	 * @param string    $new_name New theme name.
	 * @param \WP_Theme $new_theme New theme.
	 * @param \WP_Theme $old_theme Old theme.
	 * @return void
	 */
	public function theme_switched( $new_name, $new_theme, $old_theme ) {
		$this->logger->log(
			'system.theme_switch',
			array(
					'object_type' => 'theme',
					'severity'    => Logger::SEVERITY_NOTICE,
					/* translators: %s: theme name. */
					'message'     => sprintf( __( 'Theme switched to "%s".', 'tracevault-audit-log' ), $new_name ),
				'meta'        => array(
					'old_theme' => $old_theme instanceof \WP_Theme ? $old_theme->get( 'Name' ) : '',
					'new_theme' => $new_theme instanceof \WP_Theme ? $new_theme->get( 'Name' ) : $new_name,
				),
			)
		);
	}

	/**
	 * Logs option updates.
	 *
	 * @param string $option Option key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public function option_updated( $option, $old_value, $value ) {
		$this->log_option_change( $option, $old_value, $value, 'updated' );
	}

	/**
	 * Logs option additions.
	 *
	 * @param string $option Option key.
	 * @param mixed  $value Option value.
	 * @return void
	 */
	public function option_added( $option, $value ) {
		$this->log_option_change( $option, null, $value, 'created' );
	}

	/**
	 * Logs TraceVault custom-table setting updates.
	 *
	 * @param string $key       Setting key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public function tracevault_setting_updated( $key, $old_value, $value ) {
		$this->log_setting_change(
			'system.setting_update',
			'tracevault_setting',
			$key,
			$old_value,
			$value,
			'updated',
			/* translators: %s: setting name. */
			sprintf( __( 'TraceVault setting "%s" was updated.', 'tracevault-audit-log' ), $key )
		);
	}

	/**
	 * Logs WordPress option changes when they represent settings.
	 *
	 * @param string $option Option key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value New value.
	 * @param string $action Change action.
	 * @return void
	 */
	private function log_option_change( $option, $old_value, $value, $action ) {
		if ( $old_value === $value ) {
			return;
		}

		if ( $this->is_ignored_option( $option ) ) {
			return;
		}

		$is_woocommerce = $this->is_woocommerce_setting_option( $option );
		$is_core_setting = $this->is_core_setting_option( $option );

		if ( ! $is_woocommerce && ! $is_core_setting && ! (int) $this->settings->get( 'capture_option_updates', 0 ) ) {
			return;
		}

		$event_type  = $is_woocommerce ? 'woocommerce.setting_update' : ( $is_core_setting ? 'system.setting_update' : 'system.option_update' );
		$object_type = $is_woocommerce ? 'woocommerce_setting' : 'option';

		/* translators: %s: option name. */
		$message = sprintf( __( 'Option "%s" was updated.', 'tracevault-audit-log' ), $option );

		if ( $is_woocommerce ) {
			/* translators: %s: WooCommerce setting name. */
			$message = sprintf( __( 'WooCommerce setting "%s" was updated.', 'tracevault-audit-log' ), $option );
		}

		$this->log_setting_change( $event_type, $object_type, $option, $old_value, $value, $action, $message );
	}

	/**
	 * Stores a setting-change audit log.
	 *
	 * @param string $event_type Event type.
	 * @param string $object_type Object type.
	 * @param string $option Option or setting key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value New value.
	 * @param string $action Change action.
	 * @param string $message Log message.
	 * @return void
	 */
	private function log_setting_change( $event_type, $object_type, $option, $old_value, $value, $action, $message ) {
		$this->logger->log(
			$event_type,
			array(
				'object_type' => $object_type,
				'severity'    => Logger::SEVERITY_NOTICE,
				'message'     => $message,
				'meta'        => array(
					'option'    => sanitize_key( $option ),
					'action'    => sanitize_key( $action ),
					'old_value' => $this->setting_value_for_log( $option, $old_value ),
					'new_value' => $this->setting_value_for_log( $option, $value ),
				),
			)
		);
	}

	/**
	 * Reduces and redacts setting values before storing them in metadata.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @param int    $depth Current recursion depth.
	 * @return mixed
	 */
	private function setting_value_for_log( $key, $value, $depth = 0 ) {
		if ( $this->is_sensitive_setting_key( $key ) ) {
			return '[redacted]';
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			$value = (string) $value;
			return strlen( $value ) > 250 ? substr( $value, 0, 250 ) . '...' : $value;
		}

		if ( is_array( $value ) ) {
			if ( $depth >= 2 ) {
				return '[array]';
			}

			$clean = array();
			$count = 0;
			foreach ( $value as $nested_key => $nested_value ) {
				$count++;

				if ( $count > 20 ) {
					$clean['truncated'] = true;
					break;
				}

				$clean[ sanitize_key( (string) $nested_key ) ] = $this->setting_value_for_log( (string) $nested_key, $nested_value, $depth + 1 );
			}

			return $clean;
		}

		return '[' . gettype( $value ) . ']';
	}

	/**
	 * Checks whether a key likely contains sensitive data.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function is_sensitive_setting_key( $key ) {
		$key = strtolower( (string) $key );

		$patterns = array( 'api_key', 'apikey', 'secret', 'token', 'password', 'passwd', 'private', 'consumer_key', 'consumer_secret', 'client_secret', 'webhook_secret', 'license_key', 'salt' );

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $key, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether an option is a core WordPress setting.
	 *
	 * @param string $option Option key.
	 * @return bool
	 */
	private function is_core_setting_option( $option ) {
		$core_options = array(
			'admin_email',
			'blog_public',
			'blogdescription',
			'blogname',
			'comments_notify',
			'comment_moderation',
			'date_format',
			'default_comment_status',
			'default_ping_status',
			'default_role',
			'gmt_offset',
			'home',
			'mailserver_url',
			'moderation_notify',
			'page_for_posts',
			'page_on_front',
			'permalink_structure',
			'posts_per_page',
			'require_name_email',
			'show_on_front',
			'siteurl',
			'start_of_week',
			'time_format',
			'timezone_string',
			'users_can_register',
		);

		return in_array( (string) $option, $core_options, true );
	}

	/**
	 * Checks whether an option belongs to WooCommerce settings.
	 *
	 * @param string $option Option key.
	 * @return bool
	 */
	private function is_woocommerce_setting_option( $option ) {
		$option = (string) $option;

		return 0 === strpos( $option, 'woocommerce_' ) || 0 === strpos( $option, 'wc_' );
	}

	/**
	 * Checks whether an option update is too noisy to log.
	 *
	 * @param string $option Option key.
	 * @return bool
	 */
	private function is_ignored_option( $option ) {
		$option = (string) $option;

		if ( 'cron' === $option || 0 === strpos( $option, 'tracevault_' ) || 0 === strpos( $option, '_transient_' ) || 0 === strpos( $option, '_site_transient_' ) ) {
			return true;
		}

		$patterns = array(
			'cache',
			'elementor_atomic_cache_validity',
			'elementor_css',
			'elementor_remote_info',
			'doing_cron',
			'rewrite_rules',
			'recently_edited',
			'dashboard_widget_options',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $option, $pattern ) ) {
				return true;
			}
		}

		/**
		 * Filters whether an option update should be ignored by verbose logging.
		 *
		 * @param bool   $ignored Whether ignored.
		 * @param string $option  Option key.
		 */
		return (bool) apply_filters( 'tracevault_ignore_option_update', false, $option );
	}

	/**
	 * Logs WooCommerce order creation.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function woocommerce_order_created( $order_id ) {
		$this->logger->log(
			'woocommerce.order_create',
			array(
				'object_type' => 'shop_order',
				'object_id'   => $order_id,
				/* translators: %d: WooCommerce order ID. */
				'message'     => sprintf( __( 'WooCommerce order ID %d was created.', 'tracevault-audit-log' ), $order_id ),
			)
		);
	}

	/**
	 * Logs WooCommerce order updates.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function woocommerce_order_updated( $order_id ) {
		$this->logger->log(
			'woocommerce.order_update',
			array(
				'object_type' => 'shop_order',
				'object_id'   => $order_id,
				/* translators: %d: WooCommerce order ID. */
				'message'     => sprintf( __( 'WooCommerce order ID %d was updated.', 'tracevault-audit-log' ), $order_id ),
			)
		);
	}

	/**
	 * Logs WooCommerce order status changes.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param mixed  $order Order object.
	 * @return void
	 */
	public function woocommerce_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		unset( $order );
		$this->logger->log(
			'woocommerce.order_status_change',
			array(
				'object_type' => 'shop_order',
				'object_id'   => $order_id,
				'severity'    => Logger::SEVERITY_NOTICE,
				/* translators: 1: WooCommerce order ID, 2: old order status, 3: new order status. */
				'message'     => sprintf( __( 'WooCommerce order ID %1$d changed from %2$s to %3$s.', 'tracevault-audit-log' ), $order_id, $old_status, $new_status ),
			)
		);
	}

	/**
	 * Logs product changes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Is update.
	 * @return void
	 */
	public function woocommerce_product_saved( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->logger->log(
			$update ? 'woocommerce.product_update' : 'woocommerce.product_create',
			array(
				'object_type' => 'product',
				'object_id'   => $post_id,
				/* translators: %s: WooCommerce product title. */
				'message'     => sprintf( __( 'WooCommerce product "%s" was saved.', 'tracevault-audit-log' ), $post->post_title ),
			)
		);
	}

	/**
	 * Logs coupon changes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Is update.
	 * @return void
	 */
	public function woocommerce_coupon_saved( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->logger->log(
			$update ? 'woocommerce.coupon_update' : 'woocommerce.coupon_create',
			array(
				'object_type' => 'shop_coupon',
				'object_id'   => $post_id,
				/* translators: %s: WooCommerce coupon title. */
				'message'     => sprintf( __( 'WooCommerce coupon "%s" was saved.', 'tracevault-audit-log' ), $post->post_title ),
			)
		);
	}
}
