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


    public function __construct(PollerTaskQueue $tasks, $threads, $waitTimeOut = 1000000)
    {
        $this->threadsCount   = $threads;
        $this->threads        = array_fill(0, $threads, false);
        $this->waitTimeOut    = $waitTimeOut;
        $this->scheduled      = $tasks;
        $this->runningThreads = 0;
        $this->stopped        = false;

        $this->setupListeners();
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

        foreach ($this->threads as $threadId => $task) {
            if (false !== $task) {
                $this->dispatchEvent(self::EVENT_TASK_TERMINATED_PRE, $task);
                $task->forceStop();
                $this->runningThreads--;
                $this->threads[$threadId] = false;
                $this->dispatchEvent(self::EVENT_TASK_TERMINATED_POST, $task);
            }
        }
    }

    /**
     * Destroy all the references to tasks and listeners that we have
     * Useful for long-running scripts for cleaning circular references
     */
    public function destroy()
    {
        $this->stop();
        $this->setupListeners();
    }

    protected function pollRunning()
    {
        // Do this trick because you could stop Poller in the body one of the previous tasks
        // but when you do foreach it copies the array before iterating
        foreach ($this->threads as $threadId => $_) {
            $task = $this->threads[$threadId];

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

            $this->dispatchEvent(self::EVENT_TASK_STARTED_PRE, $task, $threadId);
            $this->threads[$threadId] = $task;
            $this->runningThreads++;
            $task->start($threadId);
            $this->dispatchEvent(self::EVENT_TASK_STARTED_POST, $task, $threadId);
        } while ( ! $this->scheduled->isEmpty() && false !== ($threadId = array_search(false, $this->threads, true)));
    }

    protected function dispatchEvent($eventName, Task $task, $threadId = null)
    {
        foreach ($this->listeners[$eventName] as $listener) {
            $listener($eventName, $task, $threadId);
        }
    }

    protected function setupListeners()
    {
        $this->listeners = [
            self::EVENT_TASK_STARTED_PRE     => [],
            self::EVENT_TASK_STARTED_POST    => [],
            self::EVENT_TASK_FINISHED        => [],
            self::EVENT_TASK_TERMINATED_PRE  => [],
            self::EVENT_TASK_TERMINATED_POST => []
        ];
    }

}
