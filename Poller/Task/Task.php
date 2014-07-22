<?php

namespace Poller\Task;

interface Task
{

    /**
     * @param int $threadId
     * @return void
     */
    public function start($threadId);

    /**
     * Return true in case task is still running and false if this task is finished.
     *
     * @return bool
     */
    public function heartbeat();

    /**
     * This method can be called more than once. If Task is not running already it should not do anything.
     *
     * @return void
     */
    public function forceStop();

}
