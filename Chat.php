<?php

namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        // 當連接建立時，要求使用者輸入名稱
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if(isset($data['type'])){
            if($data['type'] === 'name'){
                // 初始--使用者設定名稱
                if (!isset($from->name)) {
                    $from->name = $msg; // 將訊息作為使用者名稱
                    $this->clients->attach($from);
                    $this->broadcastUsersList();
                    // $welcomeMsg = json_encode(['wekcomeMsg' => $msg], JSON_UNESCAPED_SLASHES);
                    // $from->send($welcomeMsg);
                    return;
                }
            }elseif($data['type'] === 'chat'){
                $this->handleChatMessage($from, $data);

                
            }elseif($data['type'] === 'location'){
                $this->handleLocationUpdate($from, $data);
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->broadcastUsersList();
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastUsersList()
    {
        $users = [];
        foreach ($this->clients as $client) {
            $users[] = $client->name;
        }
        $userList = json_encode(['type' => 'userList', 'message' => $users], JSON_UNESCAPED_SLASHES);
        foreach ($this->clients as $client) {
            $client->send($userList);
        }
    }
    protected function handleLocationUpdate(ConnectionInterface $from, array $data) {
        $lat = $data['message']['lat'];
        $lng = $data['message']['lng'];
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $locationData = ['type' => 'location', 'message' => ['type' => 'data', 'message' => ['lat' => $lat, 'lng' => $lng, 'user' =>$from->name]]];
                print_r($locationData);
                $client->send(json_encode($locationData, JSON_UNESCAPED_SLASHES));
            }
        }
    }

    protected function handleChatMessage(ConnectionInterface $from, array $data){

        $message = $data['message'];

        foreach($this->clients as $client){
            if($client !== $from){
                $client->send(json_encode(['type' => 'chat', 'message' => [$from->name, ['type' => 'message', 'message' => $message]]], JSON_UNESCAPED_SLASHES));
            }
        }
    }

}