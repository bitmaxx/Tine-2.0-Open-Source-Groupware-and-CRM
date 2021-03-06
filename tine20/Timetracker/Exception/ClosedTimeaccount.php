<?php
/**
 * Tine 2.0
 *
 * @package     Timetracker
 * @subpackage  Exception
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 *
 */

/**
 * Deadline exception
 *
 * @package     Timetracker
 * @subpackage  Exception
 */
class Timetracker_Exception_ClosedTimeaccount extends Tinebase_Exception_AccessDenied
{
    /**
     * create new Deadline exception
     *
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = 'This Timeaccount is already closed!', $_code = 403) {
        parent::__construct($_message, $_code);
    }
}
