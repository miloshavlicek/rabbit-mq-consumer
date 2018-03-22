<?php

namespace Miloshavlicek\RabbitMqConsumer;

use Kdyby\RabbitMq\IConsumer;
use Kdyby\RabbitMq\TerminateException;
use Miloshavlicek\RabbitMqConsumer\Model\Log as RmqLogConsumer;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Abstract consumer for RabbitMQ
 */
abstract class Consumer
{

    /** @var string */
    protected $consumerTitle;

    /** @var \Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var \UDANAX\Service\Uni\ConsumerLog */
    protected $log;

    /** @var boolean */
    protected $oneRunOnly = TRUE;

    /**
     * Consumer callback
     *
     * @param AMQPMessage $msg
     */
    public function process(AMQPMessage $msg)
    {
        if (!$this->em->isOpen()) {
            // Entity manager is not open, so terminate
            throw new TerminateException;
        }

        try {
            $this->em->clear();
        } catch (\Exception $e) {
            // Cannot clear entity manager, so terminate
            throw new TerminateException;
        }

        try {
            // Try to process the message
            $flag = $this->processMessage($msg);
        } catch (\Exception $e) {
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
     */
    private function processMessage(AMQPMessage $msg)
    {
        try {
            // Try to unserialize the message body
            $body = unserialize($msg->body);
        } catch (\Exception $e) {
            // Error during processing of the message $msg
            $this->addMessage($e->getMessage(), RmqLogConsumer::STATUS_FATAL_ERROR);

            // Reject message to requeue
            // TODO: store corrupted message elsewhere
            return $this->processEnd(IConsumer::MSG_REJECT_REQUEUE);
        }

        try {
            // Try to process body of the message with the concreate implementation.
            $this->processBody($body);
        } catch (\Exception $e) {
            // Error during processing of the message $msg
            $this->addMessage($e->getMessage(), RmqLogConsumer::STATUS_FATAL_ERROR);
            return $this->processEnd(IConsumer::MSG_REJECT_REQUEUE);
        }

        // Everything is OK
        return $this->processEnd(IConsumer::MSG_ACK);
    }

    /**
     *
     * @param integer $flag IConsumer constant
     * @return integer IConsumer constant
     */
    private function processEnd($flag)
    {
        $status = RmqLogConsumer::STATUS_OK;

        try {
            $this->em->clear();
        } catch (\Exception $e) {
            $status = RmqLogConsumer::STATUS_ERROR;
            // Fatal error during em->clear();
        }

        try {
            return $this->processTermination($flag, $status);
        } catch (\Exception $e) {
            // Fatal error during termination
        }

        return $flag;
    }

    /**
     *
     * @param integer $flag
     * @param integer $status
     * @param string|NULL $message
     * @return boolean
     */
    private function processTermination($flag, $status, $message = NULL)
    {
        $this->addMessage(sprintf('Process terminated%s', !empty($message) ? (': ' . $message) : '.'), $status);
        return (bool)$flag;
    }

    /**
     *
     * @param mixed $body
     */
    protected function processBody($body)
    {
        throw new \Exception('Process body method is not implemented!');
    }

    /**
     * Log info that the consumer has been started
     * Called at the end of $this->__construct() implementation
     */
    protected function init()
    {
        $this->addMessage(sprintf('Consumer "%s" started.', $this->consumerTitle), RmqLogConsumer::STATUS_OK);
    }

    /**
     *
     * @param string $message
     * @param integer $status
     */
    public function addMessage($message, $status = NULL)
    {
        try {
            $this->log->addMessage($message, $status, $this->consumerTitle);
        } catch (\Exception $e) {
            // Unable to log message
            // TODO: log to another log?
        }
    }

}
