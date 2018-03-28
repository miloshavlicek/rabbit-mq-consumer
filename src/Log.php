<?php

namespace Miloshavlicek\RabbitMqConsumer;

use Kdyby\Doctrine\EntityManager;
use Miloshavlicek\RabbitMqConsumer\Model\Log as RmqLogConsumer;

/**
 * Service Log
 */
class Log
{

    /** @var EntityManager */
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param string $message
     * @param int|null $status
     * @param null|string $consumerTitle
     */
    public function addMessage(string $message, ?int $status = null, ?string $consumerTitle = null): void
    {
        $this->printMessage($message, $status);

        try {
            $this->logDb($message, $status, $consumerTitle);
        } catch (\Exception $e) {
            $this->printMessage('DB ERROR:' . $e->getMessage(), RmqLogConsumer::STATUS_ERROR);
        }
    }

    /**
     * @param string $message
     * @param int|null $status
     */
    private function printMessage(string $message, ?int $status = null): void
    {
        echo date('Y-m-d H:i:s') . '|' . ($status ? $status . ': ' : '') . $message . "\r\n";
    }

    /**
     * @param string $message
     * @param int|null $status
     * @param null|string $consumerTitle
     */
    private function logDb(string $message, ?int $status = null, ?string $consumerTitle = null): void
    {
        $log = new RmqLogConsumer;
        $log->consumerTitle = $consumerTitle;
        $log->message = $message;
        $log->status = $status;

        $this->em->persist($log);
        $this->em->flush();
    }

}
