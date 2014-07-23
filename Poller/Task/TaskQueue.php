<?php

namespace Poller\Task;

class TaskQueue extends \SplQueue
    implements PollerTaskQueue
{

    public function push($value)
    {
        if ( ! $value instanceof Task) {
            throw new \InvalidArgumentException("TaskQueue expects instance of Task only");
        }

        parent::push($value);
    }

    public function unshift($value)
    {
        if ( ! $value instanceof Task) {
            throw new \InvalidArgumentException("TaskQueue expects instance of Task only");
        }

        parent::unshift($value);
    }

    public function offsetSet($index, $newval)
    {
        if ( ! $newval instanceof Task) {
            throw new \InvalidArgumentException("TaskQueue expects instance of Task only");
        }

        parent::offsetSet($index, $newval);
    }

}
