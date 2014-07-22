<?php

namespace Poller\Task;

class CallbackTask implements Task
{

    protected $startCallback;

    protected $heartbeatCallback;

    protected $stopCallback;


    public function __construct(callable $start, callable $heartbeat, callable $stop)
    {
        $this->startCallback     = $start;
        $this->heartbeatCallback = $heartbeat;
        $this->stopCallback      = $stop;
    }

    public function start($threadId)
    {
        $start = $this->startCallback;
        $start($threadId);
    }

    public function heartbeat()
    {
        $heartbeat = $this->heartbeatCallback;
        return $heartbeat();
    }

    public function forceStop()
    {
        $stop = $this->stopCallback;
        $stop();
    }

}
