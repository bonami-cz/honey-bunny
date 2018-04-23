<?php

namespace Bonami\HoneyBunny;

use Bunny\Channel;
use Bunny\Client;
use React\Promise\PromiseInterface;

class Publisher {

	/** @var string */
	private $queue;
	/** @var bool */
	private $durable;
	/** @var Client */
	private $rabbitClient;
	/** @var Channel|PromiseInterface|null */
	private $channel;
	/** @var string */
	private $exchangeName;
	/** @var string */
	private $exchangeType;
	/** @var bool */
	private $initialized;
	/** @var string */
	private $deadLetterExchange;

	/**
	 * @param $queue string
	 * @param $exchangeName string
	 * @param $exchangeType string
	 * @param $durable bool
	 * @param $rabbitClient Client
	 * @param $deadLetterExchangeName string|null
	 */
	public function __construct(
		$queue,
		$exchangeName,
		$exchangeType,
		$durable,
		Client $rabbitClient,
		$deadLetterExchangeName = null
	) {
		$this->queue = $queue;
		$this->exchangeName = $exchangeName;
		$this->exchangeType = $exchangeType;
		$this->durable = $durable;
		$this->rabbitClient = $rabbitClient;
		$this->deadLetterExchange = $deadLetterExchangeName;

		$this->channel = null;
		$this->initialized = false;
	}

	/**
	 * @param $message string
	 * @return void
	 */
	public function publish($message, $routingKey = null) {
		$this->initialize();
		$headers = [
			'delivery_mode' => 2, // Persistent delivery
		];
		$routing = $routingKey ? $routingKey : $this->queue;
		$this->channel->publish($message, $headers, $this->exchangeName, $routing);
	}

	/** @return void */
	private function initialize() {
		if (!$this->initialized) {
			$this->connect();
			$this->createChannel();

			if ($this->exchangeName) {
				$this->declareExchange();
			} else {
				$this->declareQueue();
			}

			if ($this->deadLetterExchange) {
				$this->declareDeadLetterExchange();
			}

			$this->initialized = true;
		}
	}

	/** @return void */
	private function connect() {
		if (!$this->rabbitClient->isConnected()) {
			$this->rabbitClient->connect();
		}
	}

	/** @return void */
	private function createChannel() {
		$this->channel = $this->rabbitClient->channel();
	}

	/** @return void */
	private function declareDeadLetterExchange() {
		$this->channel->exchangeDeclare($this->deadLetterExchange, 'direct', false, $this->durable);
	}

	/** @return void */
	private function declareExchange() {
		$this->channel->exchangeDeclare($this->exchangeName, $this->exchangeType, false, $this->durable);
	}

	/** @return void */
	private function declareQueue() {
		$queueArguments = [];
		if ($this->deadLetterExchange) {
			$queueArguments = ['x-dead-letter-exchange' => $this->deadLetterExchange];
		}
		$this->channel->queueDeclare($this->queue, false, $this->durable, false, false, false, $queueArguments);
	}
}
