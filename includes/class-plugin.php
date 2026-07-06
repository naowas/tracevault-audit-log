<?php
/**
 * Main plugin coordinator.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots services and keeps dependencies explicit.
 */
final class Plugin {
	/**
	 * Plugin singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Database layer.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Logger engine.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Export service.
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * Returns the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db       = new DB();
		$this->settings = new Settings( $this->db );
		$this->logger   = new Logger( $this->db, $this->settings );
		$this->exporter = new Exporter( $this->db );
	}

	/**
	 * Registers all runtime hooks.
	 *
	 * @return void
	 */
	public function run() {
		( new Events( $this->logger, $this->settings ) )->register();
		( new Rest( $this->db, $this->exporter ) )->register();
		( new Scheduler( $this->db, $this->settings ) )->register();
		( new Privacy( $this->db ) )->register();

		if ( is_admin() ) {
			( new \OpenActivityLogger\Admin( $this->db, $this->settings, $this->exporter ) )->register();
		}
	}

	/**
	 * Database layer.
	 *
	 * @return DB
	 */
	public function db() {
		return $this->db;
	}

	/**
	 * Logger engine.
	 *
	 * @return Logger
	 */
	public function logger() {
		return $this->logger;
	}

	/**
	 * Settings repository.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
