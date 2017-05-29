<?php

namespace Bonami\HoneyBunny;

interface IMessageConsumedObserver {

	/** @return void */
	public function notify();

}
