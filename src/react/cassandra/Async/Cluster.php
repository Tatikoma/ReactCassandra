<?php

namespace Tatikoma\React\Cassandra\Async;

class Cluster
{
    /**
     * @var \Tatikoma\React\Cassandra\Async\Client[] List of configured clients in cluster
     */
    public $clients = [];
    /**
     * @var \React\EventLoop\LoopInterface Loop
     */
    public $loop = null;
    /**
     * @var \Tatikoma\React\Cassandra\Async\Client[] List of connected clients to cluster
     */
    public $connected = [];

    /**
     * @var int Interval of automatic reconnection to Cassndra servers
     */
    public $autoconnectInterval = 1;

    /**
     * @var string Name of cassandra keyspace (schema) to use
     */
    public $keyspace = '';

    public function __construct(\React\EventLoop\LoopInterface $loop, $serverOptions)
    {
        $this->loop = $loop;
        foreach ($serverOptions as $server) {
            $client = new \Tatikoma\React\Cassandra\Async\Client($loop, $server);
            $this->clients[] = $client;
        }
    }

    /**
     * @param string $keyspace
     * @return \React\Promise\PromiseInterface
     */
    public function connect($keyspace = '')
    {
        $this->keyspace = $keyspace;
        $onConnect = function (\Tatikoma\React\Cassandra\Async\Client $client) use (&$onConnect, &$onClose) {
            $this->connected[] = $client;
        };
        $onClose = function (\Tatikoma\React\Cassandra\Async\Client $client) use (&$onConnect, &$onClose) {
            foreach ($this->connected as $k => $v) {
                if ($v === $client) {
                    unset($this->connected[$k]);
                    $this->connected = array_values($this->connected);
                }
            }
            $this->loop->addTimer($this->autoconnectInterval, function () use ($client, &$onConnect, &$onClose) {
                if (!empty($this->keyspace)) {
                    $client->connect()->then(function (\Tatikoma\React\Cassandra\Async\Client $client) use ($onConnect, $onClose) {
                        return $client->query('USE "' . $this->keyspace . '"')->then(function () use ($client, $onConnect) {
                            $onConnect($client);;
                        }, $onClose);
                    }, $onClose);
                } else {
                    $client->connect()->then($onConnect, $onClose);
                }
            });
        };
        $promises = [];
        foreach ($this->clients as $client) {
            $client->on('error', $onClose);
            $client->on('close', $onClose);
            if (!empty($this->keyspace)) {
                $promise = $client->connect()->then(function (\Tatikoma\React\Cassandra\Async\Client $client) use ($onConnect, $onClose) {
                    return $client->query('USE "' . $this->keyspace . '"')->then(function () use ($client, $onConnect) {
                        $onConnect($client);;
                    }, $onClose);
                }, $onClose);
            } else {
                $promise = $client->connect()->then($onConnect, $onClose);
            }
            $promises[] = $promise;
        }
        return \React\Promise\any($promises);
    }

    /**
     * @param string $cql
     * @param array $params
     * @param int $consistency
     * @return \React\Promise\Promise
     */
    public function query($cql, $params = [], $consistency = \Tatikoma\React\Cassandra\Constants::CONSISTENCY_ONE)
    {
        return $this->getConnectedClient()->query($cql, $params, $consistency);
    }

    /**
     * @return \Tatikoma\React\Cassandra\Async\Client
     * @throws \Tatikoma\React\Cassandra\Exception
     */
    public function getConnectedClient()
    {
        if (count($this->connected) == 0) {
            throw new \Tatikoma\React\Cassandra\Exception('No one server in cluster are connected');
        }
        return $this->connected[mt_rand(0, count($this->connected) - 1)];
    }

    /**
     * Check whether at least one client in cluster is connected to Cassandra server
     * @return bool true if at least one client connected, otherwise false
     */
    public function isConnected()
    {
        return count($this->connected) > 0;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        foreach ($this->connected as $client) {
            $client->close();
        }
    }
}