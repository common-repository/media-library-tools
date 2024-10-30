<?php
/**
 * Special Offer.
 *
 * @package RadiusTheme\SB
 */

namespace TinySolutions\mlt\Controllers\Notice;

use TinySolutions\mlt\Traits\SingletonTrait;
use TinySolutions\mlt\Abs\Discount;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Black Friday Offer.
 */
class SpecialDiscount extends Discount {

	/**
	 * Singleton Trait.
	 */
	use SingletonTrait;

	/**
	 * @return array
	 */
	public function the_options(): array {
		return [
			'option_name'    => 'tsmlt_special_offer_2023',
			'start_date'     => '10 December 2023',
			'end_date'       => '10 January 2024',
			'notice_message' => "Don't miss out on our biggest sale of the year! Get your
						<b>Media Library Tools Pro plan</b> with <b>UP TO 20% OFF</b>! Limited time
						offer!!"
		];

	}

}
