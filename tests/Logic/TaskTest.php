<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use TForce\Logic\Task;
use TForce\Actions\{
    ActionCancel, ActionComplete, ActionReject, ActionRespond
};
use TForce\Exceptions\TForceException;

define('ROOT', getcwd());

require_once ROOT . DIRECTORY_SEPARATOR . 'vendor' .
    DIRECTORY_SEPARATOR . 'autoload.php';

/**
 * Class TaskTest
 * @package Test
 */
class TaskTest extends TestCase
{

    /** @var \TForce\Logic\Task */
    private $taskInst;
    private $test_customer_id = 2;
    private $test_executor_id = 3;

    /** @var \TForce\Actions\ActionBase */
    public $actionComplete;

    /** @var \TForce\Actions\ActionBase */
    public $actionReject;

    /** @var \TForce\Actions\ActionBase */
    public $actionRespond;

    /** @var \TForce\Actions\ActionBase */
    public $actionCancel;

    const PREFIX_STATUS = 'STATUS';
    const PREFIX_STATUSES = 'STATUSES';
    const PREFIX_ACTION = 'ACTION';
    const PREFIX_ACTIONS = 'ACTIONS';
    const PREFIX_ROLE = 'ROLE';
    const PREFIX_ROLES = 'ROLES';
    const PREFIX_MAP = 'MAP';


    public function setUp()
    {

        $this->taskInst = new Task($this->test_customer_id, $this->test_executor_id);

        $this->actionComplete =  ActionComplete::getInstance();
        $this->actionReject =  ActionReject::getInstance();
        $this->actionRespond =  ActionRespond::getInstance();
        $this->actionCancel =  ActionCancel::getInstance();

    }

    public function tearDown()
    {
        $this->taskInst = null;
    }

    /**
     * @param array $classConstants
     * @param string $filterPrefix
     * @return array Constant's collection of certain type
     */
    public static function filterClassConstants($classConstants, $filterPrefix)
    {
        return array_filter(
            $classConstants,
            function ($constName) use ($filterPrefix) {
                $constPrefix = explode('_', $constName)[0];
                return ($constPrefix === $filterPrefix) ? true : false;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param string $className
     * @return array All Constant's collections
     */
    public static function getClassConstants($className)
    {

        $allConstants = (new \ReflectionClass(Task::class))->getConstants();

        return [
            self::PREFIX_STATUS   =>
                self::filterClassConstants($allConstants, self::PREFIX_STATUS),
            self::PREFIX_STATUSES =>
                self::filterClassConstants($allConstants, self::PREFIX_STATUSES),
            self::PREFIX_ACTION   =>
                self::filterClassConstants($allConstants, self::PREFIX_ACTION),
            self::PREFIX_ACTIONS  =>
                self::filterClassConstants($allConstants, self::PREFIX_ACTIONS),
            self::PREFIX_MAP      =>
                self::filterClassConstants($allConstants, self::PREFIX_MAP)
        ];
    }

    public function testCreateTaskWithoutCustomerIdOrExecutorId()
    {
        $this->expectException(\Throwable::class);
        new Task(2);
    }

    public function testCreateTaskWithEqualCustomerIdAndExecutorId()
    {
        $this->expectException(TForceException::class);
        new Task(2, 2);
    }

    public function testCreateTaskWithStrangeStatus()
    {
        $this->expectException(TForceException::class);
        new Task(2, 2, 'ssttrraannggeeSsttaattuuss');
    }

    public function testStatusOfNewTask()
    {
        $expected = 'new';
        $actual = mb_strtolower($this->taskInst->getCurStatus());
        $this->assertEquals(
            $expected,
            $actual,
            'WRONG STATUS OF NEW TForce\Logic\TASK'
        );
    }

    /**
     * @return array DataSet for 'testGetCurStatus' test
     */
    public function dataStatusesForTask()
    {

        $onlyStatusConstants =
            self::getClassConstants(Task::class)[self::PREFIX_STATUS];

        $dataStatusesForTest = array_reduce(
            $onlyStatusConstants,
            function ($curry, $oneStatusConstant) {
                array_push($curry, [$oneStatusConstant]);
                return $curry;
            },
            array()
        );

        return $dataStatusesForTest;
    }

    /**
     * @param string $newStatus
     * @dataProvider dataStatusesForTask
     */
    public function testGetCurStatus(string $newStatus)
    {

        $reflectionClass = new \ReflectionClass(Task::class);
        $reflectionProperty = $reflectionClass->getProperty('curStatus');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->taskInst, $newStatus);
        $reflectionProperty->setAccessible(false);

        $expected = $newStatus;
        $actual = $this->taskInst->getCurStatus();
        $this->assertEquals(
            $expected,
            $actual,
            "WRONG CURRENT STATUS $newStatus"
        );
    }

    public function testGetAllStatuses()
    {

        $expected = current(
            self::getClassConstants(Task::class)[self::PREFIX_STATUSES]
        );

        $actual = $this->taskInst->getAllStatuses();

        $this->assertEquals(
            $expected,
            $actual,
            'WRONG RETURNED ALL STATUSES FROM CLASS!'
        );
    }

    public function testGetAllActions()
    {

        $reflectionObj = new \ReflectionObject($this->taskInst);
        $reflectionProperty = $reflectionObj->getProperty('actionObjects');
        $reflectionProperty->setAccessible(true);
        $expected = $reflectionProperty->getValue($this->taskInst);
        $reflectionProperty->setAccessible(false);

        $actual = $this->taskInst->getAllActions();

        $this->assertEquals(
            $expected,
            $actual,
            'WRONG RETURNED ALL ACTIONS FROM CLASS!'
        );
    }

    public function testGetActionsByStatusWithStrangeId()
    {
        $strangeUserId = -999;
        $statuses = array_keys(Task::STATUSES);

        foreach ($statuses as $oneStatus) {
            $actualObjActions =
                $this->taskInst->getActionsByStatus($strangeUserId, $oneStatus);

            $this->assertEquals(
                [],
                $actualObjActions,
                "WRONG ACTIONS FOR STATUS - $oneStatus"
            );
        }

    }

    public function testGetActionsByStatusWithStrangeStatus()
    {

        $this->expectException(TForceException::class);

        $curUserId = $this->test_executor_id;
        $strangeStatus = 'ssttrraannggeeSSttaattuuss';
        $this->taskInst->getActionsByStatus($curUserId, $strangeStatus);

    }

    public function testGetActionsByStatusWithCustomerId()
    {
        $curUser_id = $this->test_customer_id;
        $statuses = array_keys(Task::STATUSES);
        $expectedAvailableActions = [
            $this->actionCancel,
            $this->actionComplete
        ];
        $actualAvailableActions = null;

        foreach ($statuses as $oneStatus) {
            $actualAvailableActions =
                $this->taskInst->getActionsByStatus($curUser_id, $oneStatus);
        }

        $this->assertIsArray(
            $actualAvailableActions,
            'RETURNED ACTIONS BY STATUS MUST BE ARRAY'
        );

        foreach ($actualAvailableActions as $oneActualAvailableAction) {
            $this->assertTrue(
                in_array($oneActualAvailableAction, $expectedAvailableActions),
                'UNEXPECTED AVAILABLE ACTION BY STATUS WITH CUSTOMER ID'
            );
        }

    }

    public function testGetActionsByStatusWithExecutorId()
    {
        $curUser_id = $this->test_customer_id;
        $statuses = array_keys(Task::STATUSES);
        $expectedAvailableActions = [
            $this->actionRespond,
            $this->actionReject
        ];

        $actualAvailableActions = null;

        foreach ($statuses as $oneStatus) {
            $actualAvailableActions =
                $this->taskInst->getActionsByStatus($curUser_id, $oneStatus);
        }

        $this->assertIsArray(
            $actualAvailableActions,
            'RETURNED ACTIONS BY STATUS MUST BE ARRAY'
        );

        foreach ($actualAvailableActions as $oneActualAvailableAction) {
            $this->assertTrue(
                in_array($oneActualAvailableAction, $expectedAvailableActions),
                'UNEXPECTED AVAILABLE ACTION BY STATUS WITH CUSTOMER ID'
            );
        }

    }

    /**
     * @return array DataSet for 'testGetStatusAfterAction' test
     */
    public function dataStatusForAction()
    {

        $dataSet = [];

        $arrActionObjects = [
             ActionComplete::getInstance(),
             ActionCancel::getInstance(),
             ActionReject::getInstance(),
             ActionRespond::getInstance()
        ];

        $taskInst = new Task($this->test_customer_id, $this->test_executor_id);
        $map_action_status = $taskInst::$MAP_ACTION_STATUS;

        foreach ($arrActionObjects as $oneActionObj) {
            $actionInnerName = $oneActionObj->getInnerName();
            $statusForAction = $map_action_status[$actionInnerName];
            array_push($dataSet, [$oneActionObj, $statusForAction]);
        }

        return $dataSet;
    }

    /**
     * @param string $action
     * @param string $expectedStatus
     * @dataProvider dataStatusForAction
     */
    public function testGetStatusAfterAction($action, $expectedStatus)
    {

        $actualStatus = $this->taskInst->getStatusAfterAction($action);
        $this->assertEquals(
            $expectedStatus,
            $actualStatus,
            "WRONG STATUS $actualStatus AFTER ACTION " . $action->getInnerName()
        );

    }

    public function testStructureMapStatusActions()
    {

        $actualMapStatusActions = $this->taskInst->getMapStatusAction();
        $this->assertIsArray(
            $actualMapStatusActions,
            'MAP STATUS ACTIONS MUST BE ARRAY'
        );

        foreach ($actualMapStatusActions as $arrActions) {
            $this->assertIsArray($arrActions, 'ACTIONS IN MAP MUST BE ARRAY');
        }

        $expectedStatuses = array_values(
            self::getClassConstants(Task::class)[self::PREFIX_STATUS]
        );

        $actualStatusesFromMap = array_keys($actualMapStatusActions);

        sort($expectedStatuses);
        sort($actualStatusesFromMap);

        $this->assertEquals(
            $expectedStatuses,
            $actualStatusesFromMap,
            'WRONG INITIAL KEYS IN MAP STATUS ACTIONS'
        );

    }

    public function testStructureMapActionStatus()
    {

        $actualMapActionStatus = $this->taskInst->getMapActionStatus();
        $this->assertIsArray(
            $actualMapActionStatus,
            'MAP ACTION STATUS MUST BE ARRAY'
        );

        foreach ($actualMapActionStatus as $status) {
            $this->assertIsString($status, 'STATUS MUST BE STRING');
        }

        $expectedActions = [
            $this->actionCancel->getInnerName(),
            $this->actionComplete->getInnerName(),
            $this->actionReject->getInnerName(),
            $this->actionRespond->getInnerName(),
        ];

        $actualActionsFromMap = array_keys($actualMapActionStatus);

        sort($expectedActions);
        sort($actualActionsFromMap);

        $this->assertEquals(
            $expectedActions,
            $actualActionsFromMap,
            'WRONG INITIAL KEYS IN MAP ACTION STATUS'
        );
    }

}
