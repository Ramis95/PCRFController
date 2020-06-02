<?

namespace core;

use PhpAmqpLib\Connection\AMQPStreamConnection;

abstract class Rabbit
{
    protected $RabbitConnection;

    function __construct()
    {
        $this->RabbitConnection  = new AMQPStreamConnection(QUEU_HOST, QUEU_PORT, QUEU_USER, QUEU_PASSWORD);
    }

}
