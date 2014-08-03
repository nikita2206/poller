<?php

namespace Poller\Task;

class CallbackTask implements Task
{

    /**
     * @var callable
     */
    protected $startCallback;

    /**
     * @var callable
     */
    protected $heartbeatCallback;

    /**
     * @var callable
     */
    protected $stopCallback;

    /**
     * @var int
     */
    protected $threadId;


    public function __construct(callable $start, callable $heartbeat, callable $stop)
    {
        $this->startCallback     = $start;
        $this->heartbeatCallback = $heartbeat;
        $this->stopCallback      = $stop;
    }

    /**
     * @inheritdoc
     */
    public function start($threadId)
    {
        $this->threadId = $threadId;

        $start = $this->startCallback;
        $start($this, $threadId);
    }

    /**
     * @inheritdoc
     */
    public function heartbeat()
    {
        $heartbeat = $this->heartbeatCallback;
        return $heartbeat($this);
    }

    /**
     * @inheritdoc
     */
    public function forceStop()
    {
        $stop = $this->stopCallback;
        $stop($this);
    }

    /**
     * @return int
     */
    public function getThreadId()
    {
        return $this->threadId;
    }

}
