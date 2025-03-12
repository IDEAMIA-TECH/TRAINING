<?php
require 'vendor/autoload.php';
require_once '../includes/config.php';
require_once '../includes/NotificationManager.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    protected $users = [];
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nueva conexión! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if ($data['type'] === 'auth') {
            $this->users[$from->resourceId] = $data['user_id'];
            echo "Usuario {$data['user_id']} autenticado\n";
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->users[$conn->resourceId]);
        echo "Conexión {$conn->resourceId} cerrada\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
    
    public function sendNotification($user_id, $notification) {
        foreach ($this->clients as $client) {
            if (isset($this->users[$client->resourceId]) && $this->users[$client->resourceId] === $user_id) {
                $client->send(json_encode($notification));
            }
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NotificationServer()
        )
    ),
    8080
);

echo "Servidor WebSocket iniciado en el puerto 8080\n";
$server->run(); 