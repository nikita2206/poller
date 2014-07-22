<?php

namespace Poller\Task;

interface PollerTaskQueue
{

    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * @return Task
     */
    public function dequeue();

}
