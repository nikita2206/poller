<?php

namespace Poller;

use Poller\Task\PollerTaskQueue;
use Poller\Task\Task;

class Poller
{

    const EVENT_TASK_STARTED_PRE     = 'task.start.pre',
          EVENT_TASK_STARTED_POST    = 'task.start.post',
          EVENT_TASK_FINISHED        = 'task.finish',
          EVENT_TASK_TERMINATED_PRE  = 'task.terminate.pre',
          EVENT_TASK_TERMINATED_POST = 'task.terminate.post';

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

    /**
     * @var array
     */
    protected $listeners;


    public function __construct(PollerTaskQueue $tasks, $threads, $waitTimeOut = 1000)
    {
        $this->threadsCount   = $threads;
        $this->threads        = array_fill(0, $threads, false);
        $this->waitTimeOut    = $waitTimeOut;
        $this->scheduled      = $tasks;
        $this->runningThreads = 0;
        $this->stopped        = false;
        $this->listeners      = [
            self::EVENT_TASK_STARTED_PRE     => [],
            self::EVENT_TASK_STARTED_POST    => [],
            self::EVENT_TASK_FINISHED        => [],
            self::EVENT_TASK_TERMINATED_PRE  => [],
            self::EVENT_TASK_TERMINATED_POST => []
        ];
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     * @return static
     */
    public function attachListener($eventName, callable $listener)
    {
        $this->listeners[$eventName][] = $listener;

        return $this;
    }

    /**
     * Start polling
     *
     * @return void
     */
    public function run()
    {
        if ($this->stopped) {
            return;
        }

        do {
            $this->pollRunning();
            $this->tryToStartNewTasks();

            usleep($this->waitTimeOut);
        } while ( ! $this->stopped && ($this->runningThreads || ! $this->scheduled->isEmpty()));
    }

    /**
     * Stop all the tasks which are currently running
     *
     * @return void
     */
    public function stop()
    {
        $this->stopped = true;

        foreach ($this->threads as $task) {
            if (false !== $task) {
                $this->dispatchEvent(self::EVENT_TASK_TERMINATED_PRE, $task);
                $task->forceStop();
                $this->dispatchEvent(self::EVENT_TASK_TERMINATED_POST, $task);
            }
        }
    }

    protected function pollRunning()
    {
        foreach ($this->threads as $threadId => $task) {
            if (false !== $task && ! $task->heartbeat()) {
                $this->threads[$threadId] = false;
                $this->runningThreads--;
                $this->dispatchEvent(self::EVENT_TASK_FINISHED, $task);
            }
        }
    }

    protected function tryToStartNewTasks()
    {
        if ($this->scheduled->isEmpty() || false === ($threadId = array_search(false, $this->threads, true))) {
            return;
        }

        do {
            $task = $this->scheduled->dequeue();

            $this->dispatchEvent(self::EVENT_TASK_STARTED_PRE, $task);
            $this->threads[$threadId] = $task;
            $this->runningThreads++;
            $task->start($threadId);
            $this->dispatchEvent(self::EVENT_TASK_STARTED_POST, $task);
        } while ( ! $this->scheduled->isEmpty() && false !== ($threadId = array_search(false, $this->threads, true)));
    }

    protected function dispatchEvent($eventName, Task $task)
    {
        foreach ($this->listeners[$eventName] as $listener) {
            $listener($eventName, $task);
        }
    }

}
