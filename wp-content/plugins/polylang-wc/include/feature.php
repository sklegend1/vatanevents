<?php
/**
 * @package Polylang-WC
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Class to declare compatibility with a WooCommerce feature.

 * @since 1.9
 * @since 1.9.1 Renamed from `PLLWC_HPOS_Feature` to `PLLWC_Feature`.
 */
class PLLWC_Feature {

	/**
	 * Cache.
	 *
	 * @var bool[]
	 *
	 * @phpstan-var array<non-falsy-string, bool>
	 */
	private $cache = array();

	/**
	 * Unique feature id.
	 *
	 * @var string
	 *
	 * @phpstan-var non-empty-string
	 */
	private $feature_id;

	/**
	 * Condition to meet for the compatibility to be enabled along the feature.
	 *
	 * @var callable
	 */
	private $condition_to_meet;

	/**
	 * Constructor.
	 *
	 * @since 1.9.1
	 *
	 * @param string   $feature_id        Unique feature id.
	 * @param callable $condition_to_meet Condition to meet for our compatibility to be enabled along the feature.
	 *
	 * @phpstan-param non-empty-string $feature_id
	 */
	public function __construct( string $feature_id, callable $condition_to_meet ) {
		$this->feature_id        = $feature_id;
		$this->condition_to_meet = $condition_to_meet;
	}

	/**
	 * Tells if PLLWC can use the WC's feature.
	 * Must not be called before `after_setup_theme`.
	 *
	 * @since 1.9
	 *
	 * @return bool
	 */
	public function exists(): bool {
		if ( isset( $this->cache[ __FUNCTION__ ] ) ) {
			return $this->cache[ __FUNCTION__ ];
		}

		// Require WC 7.1+.
		if ( ! class_exists( FeaturesUtil::class ) ) {
			$this->cache[ __FUNCTION__ ] = false;
		} else {
			$features = FeaturesUtil::get_features( true );
			$this->cache[ __FUNCTION__ ] = ! empty( $features[ $this->feature_id ] );
		}

		return $this->cache[ __FUNCTION__ ];
	}

	/**
	 * Tells if the feature is enabled.
	 * Must not be used before {@see self::exists()}.
	 *
	 * @since 1.9
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( isset( $this->cache[ __FUNCTION__ ] ) ) {
			return $this->cache[ __FUNCTION__ ];
		}

		if ( ! $this->exists() ) {
			$this->cache[ __FUNCTION__ ] = false;
			return $this->cache[ __FUNCTION__ ];
		}

		// Check for the whole feature.
		if ( ! FeaturesUtil::feature_is_enabled( $this->feature_id ) ) {
			$this->cache[ __FUNCTION__ ] = false;
			return $this->cache[ __FUNCTION__ ];
		}

		// Check that our compatibility can be enabled.
		$this->cache[ __FUNCTION__ ] = (bool) call_user_func( $this->condition_to_meet );
		return $this->cache[ __FUNCTION__ ];
	}

	/**
	 * Declares this plugin compatible with WC's feature.
	 * Must not be called before `before_woocommerce_init`.
	 *
	 * @since 1.9
	 * @see https://github.com/woocommerce/woocommerce/blob/8.4.0/plugins/woocommerce/src/Utilities/FeaturesUtil.php#L45-L57
	 *
	 * @return bool True on success, false on error (feature doesn't exist or not inside the required hook).
	 */
	public function declare_compatibility(): bool {
		$success = FeaturesUtil::declare_compatibility( $this->feature_id, PLLWC_FILE, true );

		/**
		 * Fires after declaring the plugin compatible with a WC feature.
		 *
		 * @since 2.1.2
		 *
		 * @param PLLWC_Feature $pllwc_feature Instance of `PLLWC_Feature` for the current feature.
		 * @param bool          $success       Success of the compatibility declaration.
		 */
		do_action( "pllwc_declare_compatibility_$this->feature_id", $this, $success );

		return $success;
	}
}
