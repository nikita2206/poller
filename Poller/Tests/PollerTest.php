<?php

namespace Poller\Tests;

use Poller\Poller;
use Poller\Task\PollerTaskQueue;
use Poller\Task\Task;

class PollerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * In this test poller will be stopped right from one of the tasks and we will make sure that forceStop is called on
     * every task that is running right now
     */
    public function testForceStop()
    {
        $tasks = $this->getMockForAbstractClass(PollerTaskQueue::class, [], "", false);

        $poller = new Poller($tasks, 2, 0);

        $pollsToStop = 10;

        $task1 = $this->getMockForAbstractClass(Task::class, [], "", false);
        $task1->expects($this->once())->method("start")->with(0);
        $task1->expects($this->any())->method("heartbeat")->will($this->returnCallback(function () use (&$pollsToStop, $poller) {
            if ( ! --$pollsToStop) {
                $poller->stop();
            }

            return true;
        }));
        $task1->expects($this->once())->method("forceStop");

        $task2 = $this->getMockForAbstractClass(Task::class, [], "", false);
        $task2->expects($this->once())->method("start")->with(1);
        $task2->expects($this->any())->method("heartbeat")->will($this->returnValue(true));
        $task2->expects($this->once())->method("forceStop");

        // This task should never be started
        $task3 = $this->getMockForAbstractClass(Task::class, [], "", false);

        $isEmpty = 0;
        $tasks->expects($this->any())->method("isEmpty")->will($this->returnCallback(function () use (&$isEmpty) {
            return $isEmpty >= 2;
        }));

        $tasksArray = [$task1, $task2, $task3];
        $tasks->expects($this->exactly(2))->method("dequeue")->will($this->returnCallback(function () use (&$isEmpty, $tasksArray) {
            return $tasksArray[$isEmpty++];
        }));

        $poller->run();
    }

    public function testSwitchingTasks()
    {
        $tasks = $this->getMockForAbstractClass(PollerTaskQueue::class, [], "", false);

        $poller = new Poller($tasks, 3, 0);

        $tasksArray = [];

        /*
         * 0: 1 - -|5|6 - - -|10|12|14--
         * 1: 2 - - - - - -|8|11 --
         * 2: 3 -|4 - -|7 -|9 --|13 -
         */
        $tasksArray[] = $this->mockTask(0, 3,  1);
        $tasksArray[] = $this->mockTask(1, 7,  2);
        $tasksArray[] = $this->mockTask(2, 2,  3);
        $tasksArray[] = $this->mockTask(2, 3,  4);
        $tasksArray[] = $this->mockTask(0, 1,  5);
        $tasksArray[] = $this->mockTask(0, 4,  6);
        $tasksArray[] = $this->mockTask(2, 2,  7);
        $tasksArray[] = $this->mockTask(1, 1,  8);
        $tasksArray[] = $this->mockTask(2, 3,  9);
        $tasksArray[] = $this->mockTask(0, 2, 10);
        $tasksArray[] = $this->mockTask(1, 4, 11);
        $tasksArray[] = $this->mockTask(0, 2, 12);
        $tasksArray[] = $this->mockTask(2, 3, 13);
        $tasksArray[] = $this->mockTask(0, 4, 14);

        $currentTask = 0;
        $tasks->expects($this->any())->method("isEmpty")->will($this->returnCallback(function () use (&$currentTask, $tasksArray) {
            return $currentTask >= count($tasksArray);
        }));
        $tasks->expects($this->exactly(count($tasksArray)))->method("dequeue")->will($this->returnCallback(function () use (&$currentTask, $tasksArray) {
            return $tasksArray[$currentTask++];
        }));

        $poller->run();
    }

    public function testTaskStartedEvents()
    {
        $tasks  = $this->getMockForAbstractClass(PollerTaskQueue::class, [], "", false);
        $poller = new Poller($tasks, 2, 0);

        $currentTask = null;
        $taskState   = 0; // 0 - initial, 1 - started_pre event, 2 - start() call was made

        $poller->attachListener(Poller::EVENT_TASK_STARTED_PRE, function ($eventName, Task $task) use (&$currentTask, &$taskState) {
            $this->assertNull($currentTask);
            $this->assertSame(0, $taskState);
            $this->assertSame(Poller::EVENT_TASK_STARTED_PRE, $eventName);

            $currentTask = $task;
            $taskState = 1;
        });

        $startCallback = function (Task $task) use (&$currentTask, &$taskState) {
            return function () use ($task, &$currentTask, &$taskState) {
                $this->assertSame($task, $currentTask);
                $this->assertSame(1, $taskState);

                $taskState = 2;
            };
        };

        $poller->attachListener(Poller::EVENT_TASK_STARTED_POST, function ($eventName, Task $task) use (&$currentTask, &$taskState) {
            $this->assertSame(2, $taskState);
            $this->assertSame(Poller::EVENT_TASK_STARTED_POST, $eventName);

            $taskState   = 0;
            $currentTask = null;
        });

        /*
         * 0: 1-|3 ---
         * 1: 2- -|4--
         */
        $tasksArray   = [];
        $tasksArray[] = $this->mockTask(0, 2, 1, $startCallback);
        $tasksArray[] = $this->mockTask(1, 3, 2, $startCallback);
        $tasksArray[] = $this->mockTask(0, 4, 3, $startCallback);
        $tasksArray[] = $this->mockTask(1, 3, 4, $startCallback);

        $currentTaskKey = 0;
        $tasks->expects($this->any())->method("isEmpty")->will($this->returnCallback(function () use (&$currentTaskKey, $tasksArray) {
            return $currentTaskKey >= count($tasksArray);
        }));
        $tasks->expects($this->exactly(count($tasksArray)))->method("dequeue")->will($this->returnCallback(function () use (&$currentTaskKey, $tasksArray) {
            return $tasksArray[$currentTaskKey++];
        }));

        $poller->run();
    }

    protected function mockTask($threadIdToBePassed, $heartbeatsUntilFinish, $taskIndex, \Closure $onStart = null)
    {
        $task = $this->getMockForAbstractClass(Task::class, [], "", false);
        $expectation = $task->expects($this->once())->method("start")->with($threadIdToBePassed);
        $task->expects($this->exactly($heartbeatsUntilFinish))->method("heartbeat")->will($this->returnCallback(function () use (&$heartbeatsUntilFinish) {
            return (bool)--$heartbeatsUntilFinish;
        }));

        if ($onStart !== null) {
            $expectation->will($this->returnCallback($onStart($task)));
        }

        // Leave it for debugging
        $task->taskIndex = $taskIndex;

        return $task;
    }

}
