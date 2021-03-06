<?php

namespace Bonami\HoneyBunny;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Exception\ClientException;
use Bunny\Message;
use React\Promise\PromiseInterface;

declare(ticks = 1);

class ConsumerDaemon {

	/** @var string */
	private $queueName;
	/** @var bool */
	private $durable;
	/** @var int */
	private $prefetchSize;
	/** @var int */
	private $prefetchCount;
	/** @var IMessageConsumer */
	private $messageConsumer;
	/** @var Client */
	private $rabbitClient;
	/** @var Channel|PromiseInterface|null */
	private $channel;
	/** @var IMessageConsumedObserver|null */
	private $messageConsumedObserver;
	/** @var string|null */
	private $exchangeName;
	/** @var string|null */
	private $routingKey;
	/** @var bool */
	private $requeue;

	/**
	 * @param $queueName string
	 * @param $durable bool
	 * @param $prefetchSize int
	 * @param $prefetchCount int
	 * @param $messageConsumer IMessageConsumer
	 * @param $queueName string|null
	 * @param $exchangeName string|null
	 * @param $requeue string|null
	 * @param $rabbitClient Client
	 * @param $messageConsumedObserver IMessageConsumedObserver|null
	 */
	public function __construct(
		$queueName,
		$durable,
		$prefetchSize,
		$prefetchCount,
		IMessageConsumer $messageConsumer,
		$exchangeName = null,
		$routingKey = null,
		$requeue = true,
		Client $rabbitClient,
		IMessageConsumedObserver $messageConsumedObserver = null
	) {
		$this->queueName = $queueName;
		$this->durable = $durable;
		$this->prefetchSize = $prefetchSize;
		$this->prefetchCount = $prefetchCount;
		$this->messageConsumer = $messageConsumer;
		$this->rabbitClient = $rabbitClient;
		$this->channel = null;
		$this->messageConsumedObserver = $messageConsumedObserver;
		$this->exchangeName = $exchangeName;
		$this->routingKey = $routingKey;
	}

	/** @return void */
	public function engage() {
		$this->connect();
		$this->createChannel();
		$this->declareQueue($this->queueName);

		if ($this->exchangeName) {
			$this->bindQueue();
		}

		$this->registerSignalHandlers();

		try {
			$this->listenForMessage($this->queueName);
		} catch (ClientException $ex) {
			if ($ex->getMessage() !== "stream_select() failed.") {
				throw $ex;
			}
		}
	}

	/** @return void */
	private function registerSignalHandlers() {
		if (PHP_OS == 'Linux') {
			$stopRabbit = function () { $this->stop(); };
			pcntl_signal(SIGTERM, $stopRabbit);
			pcntl_signal(SIGINT, $stopRabbit);
		}
	}

	/**
	 * @param $queueName string
	 * @return void
	 */
	protected function listenForMessage($queueName) {
		$this->channel->run(function (Message $message, Channel $channel) {
			($result = $this->messageConsumer->consumeMessage($message))
				? $channel->ack($message)
				: $channel->nack($message, false, $this->requeue);
			$this->messageConsumedObserver->notify();
		}, $queueName);
	}

	/** @return void */
	protected function stop() {
		$this->rabbitClient->stop();
	}

	/** @return void */
	protected function connect() {
		if (!$this->rabbitClient->isConnected()) {
			$this->rabbitClient->connect();
		}
	}

	/** @return void */
	protected function createChannel() {
		$this->channel = $this->rabbitClient->channel();
		$this->channel->qos(
			$this->prefetchSize,
			$this->prefetchCount
		);
	}

	/**
	 * @param $queueName string
	 * @return void
	 */
	protected function declareQueue($queueName) {
		$this->channel->queueDeclare($queueName, false, $this->durable);
	}

	protected function bindQueue() {
		$this->channel->queueBind($this->queueName, $this->exchangeName, $this->routingKey);
	}

}
