<?php

namespace Miloshavlicek\RabbitMqConsumer;

use Exception;
use Kdyby\Doctrine\EntityManager;
use Kdyby\RabbitMq\IConsumer;
use Kdyby\RabbitMq\TerminateException;
use Miloshavlicek\RabbitMqConsumer\Model\Log as RmqLogConsumer;
use PhpAmqpLib\Message\AMQPMessage;
use UDANAX\Service\Uni\ConsumerLog;

/**
 * Abstract consumer for RabbitMQ
 */
abstract class Consumer
{

    /** @var string */
    protected $consumerTitle;

    /** @var EntityManager */
    protected $em;

    /** @var ConsumerLog */
    protected $log;

    /** @var boolean */
    protected $oneRunOnly = true;

    /**
     * Consumer callback
     *
     * @param AMQPMessage $msg
     * @return int
     */
    public function process(AMQPMessage $msg): int
    {
        if (!$this->em->isOpen()) {
            // Entity manager is not open, so terminate
            throw new TerminateException;
        }

        try {
            $this->em->clear();
        } catch (Exception $e) {
            // Cannot clear entity manager, so terminate
            throw new TerminateException;
        }

        try {
            // Try to process the message
            $flag = $this->processMessage($msg);
        } catch (Exception $e) {
            // Stop consuming, because some unhandeled exception during message process occured.
            throw new TerminateException;
        }

        if ($this->oneRunOnly) {
            throw (new TerminateException)->withResponse($flag);
        } else {
            return $flag;
        }
    }

    /**
     *
     * @param AMQPMessage $msg
     * @return int
     */
    private function processMessage(AMQPMessage $msg): int
    {
        try {
            // Try to unserialize the message body
            $body = unserialize($msg->body);
        } catch (Exception $e) {
            // Error during processing of the message $msg
            $this->addMessage($e->getMessage(), RmqLogConsumer::STATUS_FATAL_ERROR);

            // Reject message to requeue
            // TODO: store corrupted message elsewhere
            $this->processEnd();
            return IConsumer::MSG_REJECT_REQUEUE;
        }

        try {
            // Try to process body of the message with the concreate implementation.
            $this->processBody($body);
        } catch (Exception $e) {
            // Error during processing of the message $msg
            $this->addMessage($e->getMessage(), RmqLogConsumer::STATUS_FATAL_ERROR);
            $this->processEnd();
            return IConsumer::MSG_REJECT_REQUEUE;
        }

        // Everything is OK
        $this->processEnd();
        return IConsumer::MSG_ACK;
    }

    /**
     *
     * @param string $message
     * @param integer $status
     */
    public function addMessage(string $message, ?int $status = null): void
    {
        try {
            $this->log->addMessage($message, $status, $this->consumerTitle);
        } catch (Exception $e) {
            // Unable to log message
            // TODO: log to another log?
        }
    }

    private function processEnd(): void
    {
        $status = RmqLogConsumer::STATUS_OK;

        try {
            $this->em->clear();
        } catch (Exception $e) {
            $status = RmqLogConsumer::STATUS_ERROR;
            // Fatal error during em->clear();
        }

        try {
            $this->processTermination($status);
        } catch (Exception $e) {
            // Fatal error during termination
        }
    }

    /**
     *
     * @param integer $status
     * @param string|NULL $message
     */
    private function processTermination(int $status, ?string $message = null): void
    {
        $this->addMessage(sprintf('Process terminated%s', !empty($message) ? (': ' . $message) : '.'), $status);
    }

    /**
     *
     * @param array $body
     */
    abstract protected function processBody(array $body): void;

    /**
     * Log info that the consumer has been started
     * Called at the end of $this->__construct() implementation
     */
    protected function init(): void
    {
        $this->addMessage(sprintf('Consumer "%s" started.', $this->consumerTitle), RmqLogConsumer::STATUS_OK);
    }

}
