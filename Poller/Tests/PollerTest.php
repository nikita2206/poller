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

        $poller = new Poller($tasks, 2, 0);

        $task1Polls = 3;
        $task1 = $this->getMockForAbstractClass(Task::class, [], "", false);
        $task1->expects($this->once())->method("start")->with(0);
        $task1->expects($this->exactly(3))->method("heartbeat")->will($this->returnCallback(function () use (&$task1Polls) {
            return (bool)--$task1Polls;
        }));

        $task2 = $this->getMockForAbstractClass(Task::class, [], "", false);
    }

}
