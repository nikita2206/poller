
Poller
------

Poller is a library designed for polling non-blocking stuff.

[![Build Status](https://travis-ci.org/nikita2206/poller.svg?branch=master)](https://travis-ci.org/nikita2206/poller)

### Prerequisites

It's not a very common task for PHP but sometimes you really need to poll some things until they answer you something.

For example you can run other processes in the background and wait until they finish (you can use
 [poller-symfony-process](https://github.com/nikita2206/poller-symfony-process)
 package for it to work with Symfony's Process component) or you can poll sockets or streams.

### How to use

Suppose you have 10 processes that you need to run and you need to have only 3 processes running at the same time (only 3 job slots).
To implement this you will need to write an implementation of `Poller\Task\Task` interface which will represent a single process or
 you can use already implemented `ProcessTask` from [poller-symfony-process](https://github.com/nikita2206/poller-symfony-process)
 package.

This way you will just need to instantiate a new `Poller\Task\TaskQueue` and enqueue all your tasks in it.

``` php
$tasksQueue = new TaskQueue();
$tasksQueue->enqueue(new ProcessTask(new Process("command to execute")));
```

Now you need to create a new Poller instance and pass it your queue object and how many tasks do you want to run simultaneously
 (how many job slots do you have):

``` php
$poller = new Poller($tasksQueue, 3);
```

And now you're ready to run the Poller:

``` php
$poller->run();
```

### Task events

You can also use events on Poller, we have:

``` php
Poller::EVENT_TASK_STARTED_PRE
Poller::EVENT_TASK_STARTED_POST
Poller::EVENT_TASK_FINISHED
Poller::EVENT_TASK_TERMINATED_PRE
Poller::EVENT_TASK_TERMINATED_POST
```

You can attach your listeners using `attachListener` method, f.e.:

``` php
$poller->attachListener(Poller::EVENT_TASK_STARTED_PRE, function ($eventName, NamedTask $task) {
    echo "Task ", $task->getName(), " is gonna be started!", "\n";
});

$poller->attachListener(Poller::EVENT_TASK_FINISHED, function ($eventName, NamedTask $task) {
    echo "Task ", $task->getName(), " was finished!", "\n";
});
```

## Tasks queue

As you already saw, Poller needs TaskQueue to get tasks from. But TaskQueue class that comes with Poller is pretty limited
in a way that it's just a wrapper of SplQueue and it's not very extendable. What if, for example, you want to create a process
that runs some tasks forever? The best case using TaskQueue could be adding new tasks on `finish` event, but it wouldn't be very
readable.

Actually Poller accepts anything that implements `Poller\Task\PollerTaskQueue` interface. So you can easily implement infinite
queue:

``` php
use Poller\Task\PollerTaskQueue;

class InfiniteTaskQueue implements PollerTaskQueue
{

    public function isEmpty()
    {
        return false;
    }

    public function dequeue()
    {
        return new YourOwnTask();
    }

}
```
