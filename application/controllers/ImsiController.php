<?

namespace controllers;

use core\Rabbit;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ImsiController extends Rabbit
{
	private $msg;

	public function __construct()
	{
		parent::__construct();
	}

	public function listenFromQueu()
	{
		$channel = $this->RabbitConnection->channel();
		$channel->queue_declare('q_temporary_psrf', false, true, false, false);

		$callback = function ($msg) {
			$this->msg = $msg;
			$this->preparePCRFRequest();
		};

		$channel->basic_consume('q_temporary_psrf', '', false, false, false, false,
			$callback);

		while (count($channel->callbacks)) {
			$channel->wait();
		}

		$channel->close();
		$this->RabbitConnection->close();
	}

	private function preparePCRFRequest()
	{

		$msg_body = json_decode($this->msg->body);
		$url = 'http://192.168.143.182/v1/imsi/';

		if($msg_body->command == 'add_policy')
		{
			$url .= 'add-policy';
		}
		elseif ($msg_body->command == 'delete_policy')
		{
			$url .= 'delete-policy';
		}
		elseif ($msg_body->command == 'delete')
		{
			$url .= 'delete';
		}

//		$url = 'http://192.168.143.182/v1/imsi/add-policy';
//		$data = [
//			'imsi'        => '250270100182859',
//			'msisdn'      => 89999999999,
//			'description' => 'Успех',
//			'policies'    => [
//				'policies string',
//			],
//		];

		$data = json_encode($msg_body->params);
		$PCRF_result = $this->sendToPCRF($url, $data);
		$PCRF_result['msg'] = $msg_body->params;

		$this->addToQueu(json_encode($PCRF_result));
	}

	private function sendToPCRF($url, $json)
	{
		$result = [];

		$ch   = curl_init($url);

		curl_setopt($ch, CURLOPT_POST, true); //переключаем запрос в POST
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json); //Это POST данные
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,
			false); //Отключим проверку сертификата https
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($json),
			)
		);

		$result['response_data'] = curl_exec($ch);
		$result['response_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Получаем HTTP-код

		if ($result['response_code'] == 200)
		{
			$result['response_data'] = 'Success';
		}

		curl_close($ch);
		return $result;

	}

	private function addToQueu($pcrf_response)
	{

		$RB_connection = new AMQPStreamConnection(QUEU_HOST, QUEU_PORT, QUEU_USER, QUEU_PASSWORD);
		$RB_channel = $RB_connection->channel();
		$RB_channel->queue_declare('pcrf_response', false, true, false, false);

		$pcrf_msg = new AMQPMessage($pcrf_response, ['delivery_mode' => 2]);

		$RB_channel->basic_publish($pcrf_msg, 'ex_pcrf_response', 'key_pcrf_response');
		$RB_channel->close();
		$RB_connection->close();


		$this->msg->delivery_info['channel']->basic_ack($this->msg->delivery_info['delivery_tag']); // Удаляем сообщение из очереди только после добавления ответа в другую очередь(IMSI)

	}

}
