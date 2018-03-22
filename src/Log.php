<?php

namespace Miloshavlicek\RabbitMqConsumer;

use Miloshavlicek\RabbitMqConsumer\Model\Log as RmqLogConsumer;

/**
 * Service Log
 */
class Log
{

    /** @var \Kdyby\Doctrine\EntityManager */
    private $em;

    public function __construct(\Kdyby\Doctrine\EntityManager $em)
    {
        $this->em = $em;
    }

    public function addMessage(string $message, $status = NULL, ?string $consumerTitle = NULL)
    {
        $this->printMessage($message, $status);

        try {
            $this->logDb($message, $status, $consumerTitle);
        } catch (\Exception $e) {
            $this->printMessage('DB ERROR:' . $e->getMessage(), RmqLogConsumer::STATUS_ERROR);
        }
    }

    private function printMessage(string $message, $status = NULL)
    {
        echo date('Y-m-d H:i:s') . '|' . ($status ? $status . ': ' : '') . $message . "\r\n";
    }

    private function logDb(string $message, $status = NULL, ?string $consumerTitle = NULL)
    {
        $log = new RmqLogConsumer;
        $log->consumerTitle = $consumerTitle;
        $log->message = $message;
        $log->status = $status;

        $this->em->persist($log);
        $this->em->flush();
    }

}
