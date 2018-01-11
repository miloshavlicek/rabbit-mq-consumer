<?php

namespace Miloshavlicek\RabbitMqConsumer\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rabbit MQ Log for consumers
 * 
 * @ORM\Entity
 * @ORM\Table(name="c_rmq_log_consumer")
 */
class Log {

    use \Kdyby\Doctrine\Entities\Attributes\Identifier;
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    const STATUS_OK = 1;
    const STATUS_WARNING = 2;
    const STATUS_ERROR = 3;
    const STATUS_FATAL_ERROR = 4;

    /**
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $consumerTitle;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $message;

    /**
     * @ORM\Column(type="integer", length=2, nullable=true)
     */
    public $status;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $logTime;

    public function __construct() {
        $this->logTime = new \DateTime();
    }

    public function getId() {
        return $this->id;
    }

    public function __clone() {
        $this->id = NULL;
    }

}
