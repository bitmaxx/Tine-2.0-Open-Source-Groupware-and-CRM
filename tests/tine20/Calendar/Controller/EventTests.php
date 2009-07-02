<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @version     $Id$
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Calendar_Controller_EventTests::main');
}

/**
 * Test class for Calendar_Controller_Event
 * 
 * @package     Calendar
 */
class Calendar_Controller_EventTests extends PHPUnit_Framework_TestCase
{
    
    /**
     * @var Calendar_Controller_Event controller unter test
     */
    protected $_controller;
    
    /**
     * @var Tinebase_Model_Container
     */
    protected $_testCalendar;
    
    public function setUp()
    {
        $this->_controller = Calendar_Controller_Event::getInstance();
        $this->_testCalendar = Tinebase_Container::getInstance()->addContainer(new Tinebase_Model_Container(array(
            'name'           => 'PHPUnit test calendar',
            'type'           => Tinebase_Model_Container::TYPE_PERSONAL,
            'backend'        => 'sometype',
            'application_id' => Tinebase_Application::getInstance()->getApplicationByName('Calendar')->getId()
        ), true));
    }
    
    public function tearDown()
    {
        $eventIds = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'container_id', 'operator' => 'equals', 'value' => $this->_testCalendar->getId()),
        )), new Tinebase_Model_Pagination(array()), false, true);
        
        $this->_controller->delete($eventIds);
        
        Tinebase_Container::getInstance()->deleteContainer($this->_testCalendar, true);
    }
    
    public function testCreateEvent()
    {
        $event = $this->_getEvent();
        $persitentEvent = $this->_controller->create($event);
        
        $this->assertEquals($event->description, $persitentEvent->description);
        $this->assertTrue($event->dtstart->equals($persitentEvent->dtstart));
        $this->assertEquals(Tinebase_Core::get(Tinebase_Core::USERTIMEZONE), $persitentEvent->originator_tz);
        
        return $persitentEvent;
    }
    
    public function testGetEvent()
    {
        $persitentEvent = $this->testCreateEvent();
        $this->assertTrue((bool) $persitentEvent->readGrant);
        $this->assertTrue((bool) $persitentEvent->editGrant);
        $this->assertTrue((bool) $persitentEvent->deleteGrant);
        
        $loadedEvent = $this->_controller->get($persitentEvent->getId());
        $this->assertTrue((bool) $loadedEvent->readGrant);
        $this->assertTrue((bool) $loadedEvent->editGrant);
        $this->assertTrue((bool) $loadedEvent->deleteGrant);
    }
    
    public function testUpdateEvent()
    {
        $persitentEvent = $this->testCreateEvent();
        
        $currentTz = Tinebase_Core::get(Tinebase_Core::USERTIMEZONE);
        Tinebase_Core::set(Tinebase_Core::USERTIMEZONE, 'farfaraway');
        
        $persitentEvent->summary = 'Lunchtime';
        $updatedEvent = $this->_controller->update($persitentEvent);
        $this->assertEquals($persitentEvent->summary, $updatedEvent->summary);
        $this->assertEquals($currentTz, $updatedEvent->originator_tz, 'originator_tz must not be touchet if dtsart is not updatet!');
        
        $updatedEvent->dtstart->addHour(1);
        $updatedEvent->dtend->addHour(1);
        $secondUpdatedEvent = $this->_controller->update($updatedEvent);
        $this->assertEquals(Tinebase_Core::get(Tinebase_Core::USERTIMEZONE), $secondUpdatedEvent->originator_tz, 'originator_tz must be adopted if dtsart is updatet!');
    
        Tinebase_Core::set(Tinebase_Core::USERTIMEZONE, $currentTz);
    }
    
    public function testAttendeeBasics()
    {
        $event = $this->_getEvent();
        $event->attendee = $this->_getAttendee();
        
        $persistendEvent = $this->_controller->create($event);
        $this->assertEquals(2, count($persistendEvent->attendee));
        
        unset($persistendEvent->attendee[0]);
        $updatedEvent = $this->_controller->update($persistendEvent);
        $this->assertEquals(1, count($updatedEvent->attendee));
        
        sleep(1);
        $updatedEvent->attendee->getFirstRecord()->role = Calendar_Model_Attender::ROLE_OPTIONAL;
        $secondUpdatedEvent = $this->_controller->update($updatedEvent);
        $this->assertEquals(1, count($secondUpdatedEvent->attendee));
        $this->assertEquals(Calendar_Model_Attender::ROLE_OPTIONAL, $secondUpdatedEvent->attendee->getFirstRecord()->role);
    }
    
    public function testAttendeeAuthKeyPreserv()
    {
        $event = $this->_getEvent();
        $event->attendee = $this->_getAttendee();
        
        $persistendEvent = $this->_controller->create($event);
        $newAuthKey = Tinebase_Record_Abstract::generateUID();
        $persistendEvent->attendee->status_authkey = $newAuthKey;
        
        $updatedEvent = $this->_controller->update($persistendEvent);
        foreach ($updatedEvent->attendee as $attender) {
            $this->assertNotEquals($newAuthKey, $attender->status_authkey);
        }
    }
    
    public function testAttendeeStatusViaSave()
    {
        $event = $this->_getEvent();
        $event->attendee = $this->_getAttendee();
        $event->attendee[0]->user_id = Tinebase_User::getInstance()->getUserByLoginName('sclever')->getId();
        $event->attendee[0]->status = Calendar_Model_Attender::STATUS_ACCEPTED;
        unset($event->attendee[1]);
        
        $persistendEvent = $this->_controller->create($event);
        $this->assertEquals(Calendar_Model_Attender::STATUS_NEEDSACTION, $persistendEvent->attendee[0]->status, 'creation of other attedee must not set status');
        
        $persistendEvent->attendee[0]->status = Calendar_Model_Attender::STATUS_ACCEPTED;
        $updatedEvent = $this->_controller->update($persistendEvent);
        $this->assertEquals(Calendar_Model_Attender::STATUS_NEEDSACTION, $updatedEvent->attendee[0]->status, 'updateing of other attedee must not set status');
    }
    
    public function testSetAttendeeStatus()
    {
        $event = $this->_getEvent();
        $event->attendee = $this->_getAttendee();
        unset($event->attendee[1]);
        
        $persistendEvent = $this->_controller->create($event);
        $attendee = $persistendEvent->attendee[0];
        
        $attendee->status = Calendar_Model_Attender::STATUS_DECLINED;
        $this->_controller->setAttenderStatus($persistendEvent, $attendee, $attendee->status_authkey);
        
        $loadedEvent = $this->_controller->get($persistendEvent->getId());
        $this->assertEquals(Calendar_Model_Attender::STATUS_DECLINED, $loadedEvent->attendee[0]->status, 'status not set');
        
    }
    
    public function testSetAttendeeStatusImplicitRecurException()
    {
        // note: 2009-03-29 Europe/Berlin switched to DST
        $event = new Calendar_Model_Event(array(
            'uid'           => Tinebase_Record_Abstract::generateUID(),
            'summary'       => 'Abendessen',
            'dtstart'       => '2009-03-25 18:00:00',
            'dtend'         => '2009-03-25 18:30:00',
            'originator_tz' => 'Europe/Berlin',
            'rrule'         => 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-03-31 17:30:00',
            'exdate'        => '2009-03-27 18:00:00,2009-03-29 17:00:00',
            'container_id'  => $this->_testCalendar->getId(),
            'editGrant'     => true,
        ));
        $event->attendee = $this->_getAttendee();
        unset($event->attendee[1]);
        
        $persitentEvent = $this->_controller->create($event);
        $attendee = $persitentEvent->attendee[0];
        
        $exceptions = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $from = new Zend_Date('2009-03-26 00:00:00', Tinebase_Record_Abstract::ISO8601LONG);
        $until = new Zend_Date('2009-04-01 23:59:59', Tinebase_Record_Abstract::ISO8601LONG);
        $recurSet = Calendar_Model_Rrule::computeRecuranceSet($persitentEvent, $exceptions, $from, $until);
        
        $exception = $recurSet->getFirstRecord();
        $attendee = $exception->attendee[0];
        $attendee->status = Calendar_Model_Attender::STATUS_ACCEPTED;
        
        $this->_controller->setAttenderStatus($exception, $attendee, $attendee->status_authkey);
        
        $events = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'period', 'operator' => 'within', 'value' => array('from' => $from, 'until' => $until)),
            array('field' => 'uid', 'operator' => 'equals', 'value' => $persitentEvent->uid)
        )));
        $this->assertEquals(2, count($events));
    }
    
    public function testUpdateRecuingDtstart()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $event->exdate = array(new Zend_Date('2009-04-07 13:00:00', Tinebase_Record_Abstract::ISO8601LONG));
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(2);
        $exception->dtend->addDay(2);
        $exception->setId(NULL);
        unset($exception->rrule);
        unset($exception->exdate);
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentException = $this->_controller->create($exception);
        
        $persitentEvent->dtstart->addHour(5);
        $persitentEvent->dtend->addHour(5);
        
        $updatedEvent = $this->_controller->update($persitentEvent);
        $updatedException = $this->_controller->get($persitentException->getId());
        $this->assertEquals('2009-04-07 18:00:00', $updatedEvent->exdate[0]->get(Tinebase_Record_Abstract::ISO8601LONG), 'failed to update exdate');
        $this->assertEquals('2009-04-08 18:00:00', substr($updatedException->recurid, -19), 'failed to update persistent exception');
        $this->assertEquals('2009-04-30 18:30:00', Calendar_Model_Rrule::getRruleFromString($updatedEvent->rrule)->until->get(Tinebase_Record_Abstract::ISO8601LONG), 'failed to update until in rrule');
        $this->assertEquals('2009-04-30 18:30:00', $updatedEvent->rrule_until->get(Tinebase_Record_Abstract::ISO8601LONG), 'failed to update rrule_until');
        
        sleep(1); // wait for modlog
        $updatedEvent->dtstart->subHour(5);
        $updatedEvent->dtend->subHour(5);
        $secondUpdatedEvent = $this->_controller->update($updatedEvent);
        $secondUpdatedException = $this->_controller->get($persitentException->getId());
        $this->assertEquals('2009-04-07 13:00:00', $secondUpdatedEvent->exdate[0]->get(Tinebase_Record_Abstract::ISO8601LONG), 'failed to update exdate (sub)');
        $this->assertEquals('2009-04-08 13:00:00', substr($secondUpdatedException->recurid, -19), 'failed to update persistent exception (sub)');
    }
    
    public function testUpdateRecurDtstartOverDst()
    {
        // note: 2009-03-29 Europe/Berlin switched to DST
        $event = new Calendar_Model_Event(array(
            'uid'           => Tinebase_Record_Abstract::generateUID(),
            'summary'       => 'Abendessen',
            'dtstart'       => '2009-03-25 18:00:00',
            'dtend'         => '2009-03-25 18:30:00',
            'originator_tz' => 'Europe/Berlin',
            'rrule'         => 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-02 17:30:00',
            'exdate'        => '2009-03-27 18:00:00,2009-03-31 17:00:00',
            'container_id'  => $this->_testCalendar->getId(),
            'editGrant'     => true,
        ));
        
        $persitentEvent = $this->_controller->create($event);
        
        $exceptions = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $from = new Zend_Date('2009-03-26 00:00:00', Tinebase_Record_Abstract::ISO8601LONG);
        $until = new Zend_Date('2009-04-03 23:59:59', Tinebase_Record_Abstract::ISO8601LONG);
        $recurSet = Calendar_Model_Rrule::computeRecuranceSet($persitentEvent, $exceptions, $from, $until); // 9 days
        
        // skip 27(exception), 31(exception), 03(until)
        $this->assertEquals(6, count($recurSet));
        
        
        $exceptionBeforeDstBoundary = clone $recurSet[1]; // 26. 
        $persistentExceptionBeforeDstBoundary = $this->_controller->createRecurException($exceptionBeforeDstBoundary);
        
        $exceptionAfterDstBoundary = clone $recurSet[5]; // 02.
        $persistentExceptionAfterDstBoundary = $this->_controller->createRecurException($exceptionAfterDstBoundary);
        
        $persitentEvent->dtstart->addDay(5); //30.
        $persitentEvent->dtend->addDay(5);
        $from->addDay(5); //31
        $until->addDay(5); //08
        
        // NOTE: with this, also until, and exceptions get moved, but not the persistent exceptions
        $updatedPersistenEvent = $this->_controller->update($persitentEvent);
        
        $persistentEvents = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'period', 'operator' => 'within', 'value' => array('from' => $from, 'until' => $until)),
            array('field' => 'uid', 'operator' => 'equals', 'value' => $persitentEvent->uid)
        )));
        // we don't 'see' the persistent exception from 26/
        $this->assertEquals(2, count($persistentEvents));
                
        $exceptions = $persistentEvents->filter('recurid', "/^{$persitentEvent->uid}-.*/", TRUE);
        $recurSet = Calendar_Model_Rrule::computeRecuranceSet($updatedPersistenEvent, $exceptions, $from, $until);
        
        // skip 31(exception), and 8 (until)
        $this->assertEquals(7, count($recurSet));
    }
    
    public function testUpdateImplicitDeleteRcuringExceptions()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $event->exdate = array(new Zend_Date('2009-04-07 13:00:00', Tinebase_Record_Abstract::ISO8601LONG));
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(2);
        $exception->dtend->addDay(2);
        $exception->setId(NULL);
        unset($exception->rrule);
        unset($exception->exdate);
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentException = $this->_controller->create($exception);
        
        unset($persitentEvent->rrule);
        $updatedEvent = $this->_controller->update($persitentEvent);
        $this->assertNull($updatedEvent->exdate);
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $this->_controller->get($persitentException->getId());
    }
    
    public function testDeleteEvent()
    {
        $event = $this->_getEvent();
        $persitentEvent = $this->_controller->create($event);
        
        $this->_controller->delete($persitentEvent->getId());
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $this->_controller->get($persitentEvent->getId());
    }
    
    /**
     * @todo use exception api once we have it!
     *
     */
    public function testDeleteRecurExceptions()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $event->exdate = array(new Zend_Date('2009-04-07 13:00:00', Tinebase_Record_Abstract::ISO8601LONG));
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(2);
        $exception->dtend->addDay(2);
        $exception->setId(NULL);
        unset($exception->rrule);
        unset($exception->exdate);
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentException = $this->_controller->create($exception);
        
        $this->_controller->delete($persitentEvent->getId());
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $this->_controller->get($persitentException->getId());
    }
    
    public function testCreateRecurException()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(3);
        $exception->dtend->addDay(3);
        $exception->summary = 'Abendbrot';
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentException = $this->_controller->createRecurException($exception);
        
        $persitentEvent = $this->_controller->get($persitentEvent->getId());
        $this->assertNull($persitentEvent->exdate);
        $events = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'uid',     'operator' => 'equals', 'value' => $persitentEvent->uid),
        )));
        $this->assertEquals(2, count($events));
    }
    
    public function testDeleteNonPersistentRecurException()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(3);
        $exception->dtend->addDay(3);
        $exception->summary = 'Abendbrot';
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentEventWithExdate = $this->_controller->createRecurException($exception, true);
        
        $persitentEvent = $this->_controller->get($persitentEvent->getId());
        $this->assertType('Zend_Date', $persitentEventWithExdate->exdate[0]);
        $this->assertEquals($persitentEventWithExdate->exdate, $persitentEvent->exdate);
        $events = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'uid',     'operator' => 'equals', 'value' => $persitentEvent->uid),
        )));
        $this->assertEquals(1, count($events));
    }
    
    public function testDeletePersistentRecurException()
    {
        $event = $this->_getEvent();
        $event->rrule = 'FREQ=DAILY;INTERVAL=1;UNTIL=2009-04-30 13:30:00';
        $persitentEvent = $this->_controller->create($event);
        
        $exception = clone $persitentEvent;
        $exception->dtstart->addDay(3);
        $exception->dtend->addDay(3);
        $exception->summary = 'Abendbrot';
        $exception->recurid = $exception->uid . '-' . $exception->dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $persitentException = $this->_controller->createRecurException($exception);
        
        $this->_controller->delete($persitentException->getId());
        
        $persitentEvent = $this->_controller->get($persitentEvent->getId());
        $this->assertType('Zend_Date', $persitentEvent->exdate[0]);
        $events = $this->_controller->search(new Calendar_Model_EventFilter(array(
            array('field' => 'uid',     'operator' => 'equals', 'value' => $persitentEvent->uid),
        )));
        $this->assertEquals(1, count($events));
    }
    
    /**
     * returns a simple event
     *
     * @return Calendar_Model_Event
     */
    protected function _getEvent()
    {
        return new Calendar_Model_Event(array(
            'summary'     => 'Mittagspause',
            'dtstart'     => '2009-04-06 13:00:00',
            'dtend'       => '2009-04-06 13:30:00',
            'description' => 'Wieslaw Brudzinski: Das Gesetz garantiert zwar die Mittagspause, aber nicht das Mittagessen...',
        
            'container_id' => $this->_testCalendar->getId(),
            'editGrant'    => true,
        ));
    }
    
    protected function _getAttendee()
    {
        return new Tinebase_Record_RecordSet('Calendar_Model_Attender', array(
            array(
                'user_id'   => Tinebase_Core::getUser()->getId(),
                'role'      => Calendar_Model_Attender::ROLE_REQUIRED
            ),
            array(
                'user_id'   => Tinebase_Core::getUser()->accountPrimaryGroup,
                'user_type' => Calendar_Model_Attender::USERTYPE_GROUP
            )
        ));
    }
    
}
    

if (PHPUnit_MAIN_METHOD == 'Calendar_Controller_EventTests::main') {
    Calendar_Controller_EventTests::main();
}
