<?php

namespace Bonami\HoneyBunny;

use Bunny\Message;

interface IMessageConsumer {

	/**
	 * @param $message Message
	 * @return bool
	 */
	public function consumeMessage(Message $message);

}
