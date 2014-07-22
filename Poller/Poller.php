<?php

namespace Poller;

use Poller\Task\PollerTaskQueue;
use Poller\Task\Task;

class Poller
{

    /**
     * @var PollerTaskQueue|Task[]
     */
    protected $scheduled;

    /**
     * @var int
     */
    protected $waitTimeOut;

    /**
     * @var int
     */
    protected $threadsCount;

    /**
     * @var Task[]
     */
    protected $threads;

    /**
     * @var int
     */
    protected $runningThreads;

    /**
     * @var bool
     */
    protected $stopped;


    public function __construct(PollerTaskQueue $tasks, $threads, $waitTimeOut = 1000)
    {
        $this->threadsCount   = $threads;
        $this->threads        = array_fill(0, $threads, false);
        $this->waitTimeOut    = $waitTimeOut;
        $this->scheduled      = $tasks;
        $this->runningThreads = 0;
        $this->stopped        = false;
    }

    public function run()
    {
        if ($this->stopped) {
            return;
        }

        do {
            $this->pollRunning();
            $this->tryToRunNewTasks();

            usleep($this->waitTimeOut);
        } while ( ! $this->stopped && ($this->runningThreads || ! $this->scheduled->isEmpty()));
    }

    public function stop()
    {
        $this->stopped = true;

        foreach ($this->threads as $task) {
            if (false !== $task) {
                $task->forceStop();
            }
        }
    }

    protected function pollRunning()
    {
        foreach ($this->threads as $threadId => $task) {
            if (false !== $task && ! $task->heartbeat()) {
                $this->threads[$threadId] = false;
                $this->runningThreads--;
            }
        }
    }

    protected function tryToRunNewTasks()
    {
        if ($this->scheduled->isEmpty() || false === ($threadId = array_search(false, $this->threads, true))) {
            return;
        }

        do {
            $task = $this->scheduled->dequeue();

            $this->threads[$threadId] = $task;
            $this->runningThreads++;
            $task->start($threadId);
        } while ( ! $this->scheduled->isEmpty() && false !== ($threadId = array_search(false, $this->threads, true)));
    }

}
