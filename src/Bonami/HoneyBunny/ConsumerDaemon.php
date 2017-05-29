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

	/**
	 * @param $queueName string
	 * @param $durable bool
	 * @param $prefetchSize int
	 * @param $prefetchCount int
	 * @param $messageConsumer IMessageConsumer
	 * @param $rabbitClient Client
	 * @param $messageConsumedObserver IMessageConsumedObserver|null
	 */
	public function __construct(
		$queueName,
		$durable,
		$prefetchSize,
		$prefetchCount,
		IMessageConsumer $messageConsumer,
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
	}

	/** @return void */
	public function engage() {
		$this->connect();
		$this->createChannel();
		$this->declareQueue($this->queueName);
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
				: $channel->nack($message);
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

}
