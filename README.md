# honey-bunny

## Basic usage example
For this very simple example no special settings for rabbit were used. 
Also messages here are just serialized json payloads, but might be messages with some better structures
(event like or command like).

For simplicity no DI container wiring here is used, but since library is DI friendly, constucting
consumers, publishers and daemons might by done via DIC or Factories.

```php
<?php

use Bonami\HoneyBunny\Publisher;
use DateTimeImmutable;

class PingPublisher {

	private $publisher;

	public function __construct(
		Publisher $publisher
	) {
		$this->publisher = $publisher;
	}

	public function ping($clientId, DateTimeImmutable $now) {
		$this->publisher->publish(json_encode(['clientId' => $clientId, 'time' => $now->getTimestamp()]));
	}

}
```

```php
<?php

use Bonami\HoneyBunny\IMessageConsumer;
use Bunny\Message;

class PingConsumer implements IMessageConsumer {

	/** @inheritdoc */
	public function consumeMessage(Message $message) {
		$json = json_decode($message->content, true);
		if (!(array_key_exists('clientId', $json) && array_key_exists('time', $json))) {
			// malformed message, there I can decide whether to ignore it with return true (causes ACK)
			// or try to process it again with return false (causes NACK)
			// throwing an exception will cause throwing a NACK as well

			return true; // ignoring malformed messages, there should be logging of those rejected.
		}
		// For simplicity just print it out
		var_dump(sprintf('client: %d sent ping in time %d', $json['clientId'], $json['time']));

		return true;
	}
}
```

```php
<?php

use Bonami\HoneyBunny\PingPublisher;
use Bonami\HoneyBunny\Publisher;
use Bunny\Client;

// there should be some arguments validation, later $argv[1] is used.

// ping script
$rabbitClient = new Client(/* connection options goes there */);

// For simple messaging, it is enough to specify only queue name pass empty strings as exchangeName & "direct" as exchangeType
$pinger = new PingPublisher(new Publisher('ping.queue', '', 'direct', true, $rabbitClient));
foreach (range(1, 10) as $_) {
	$pinger->ping($argv[1], new DateTimeImmutable());
}
```

```php
<?php

use Bunny\Client;
use Bonami\HoneyBunny\ConsumerDaemon;
use Bonami\HoneyBunny\DummyMessageConsumedObserver;

// pong daemon script
$rabbitClient = new Client(/* connection options goes there */);
$pingListener = new ConsumerDaemon(
	'ping.queue',
	true,
	1,
	1,
	new PingConsumer(),
	$rabbitClient
);
$pingListener->engage();
```

## Observers

Observers for consumer daemon are here designed to easily hook repeated operations after
message consuming. Typical operations are clearing doctrine identity map or garbage collection.

Here is simple example of garbage collector.
```php
<?php

use Bonami\HoneyBunny\IMessageConsumedObserver;
use Bonami\HoneyBunny\MultiMessageConsumedObserver;

class GarbageCollectObserver implements IMessageConsumedObserver {

	public function notify() {
		gc_collect_cycles();
	}

}

```

## Quick development with docker
```
docker run -it --rm -v $(pwd):/app composer/composer install
docker run -it --rm -v $(pwd):$(pwd) -w $(pwd) php:7.1-cli php -d memory_limit=-1 vendor/bin/phpstan analyse -c phpstan.neon --ansi -l 5 src/
```

## TODO
Basic unit tests and CI integration
