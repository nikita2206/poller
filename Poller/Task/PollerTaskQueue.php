<?php

namespace Poller\Task;

interface PollerTaskQueue
{

    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * Return new Task if the queue is not empty
     *
     * @return Task
     */
    public function dequeue();

}
