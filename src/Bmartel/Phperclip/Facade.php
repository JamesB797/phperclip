<?php namespace Bmartel\Phperclip;

use Illuminate\Support\Facades\Facade as Base;

class Facade extends Base {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() {
		return 'Bmartel\Phperclip\Service';
	}

}