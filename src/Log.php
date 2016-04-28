<?php

namespace Miloshavlicek\RabbitMqConsumer;

use Miloshavlicek\RabbitMqConsumer\Model\Log as RmqLogConsumer;
use Nette\Object as NObject;

/**
 * Service Log
 */
class Log extends NObject {

    /** @var \Kdyby\Doctrine\EntityManager */
    private $em;

    public function __construct(\Kdyby\Doctrine\EntityManager $em) {
        $this->em = $em;
    }

    public function addMessage($message, $status = NULL, $consumerTitle = NULL) {
        $this->printMessage($message, $status);

        try {
            $this->logDb($message, $status, $consumerTitle);
        } catch (\Exception $e) {
            $this->printMessage('DB ERROR:' . $e->getMessage(), RmqLogConsumer::STATUS_ERROR);
        }
    }

    private function logDb($message, $status = NULL, $consumerTitle = NULL) {
        $log = new RmqLogConsumer;
        $log->consumerTitle = $consumerTitle;
        $log->message = $message;
        $log->status = $status;

        $this->em->persist($log);
        $this->em->flush();
    }

    private function printMessage($message, $status = NULL) {
        echo date('Y-m-d H:i:s') . '|' . ($status ? $status . ': ' : '') . $message . "\r\n";
    }

}
