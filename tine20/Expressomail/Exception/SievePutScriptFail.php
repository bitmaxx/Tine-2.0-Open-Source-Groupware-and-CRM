<?php
/**
 * Tine 2.0
 * 
 * @package     Expressomail
 * @subpackage  Exception
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 *
 */

/**
 * Put Sieve Script Failed Exception
 * 
 * @package     Expressomail
 * @subpackage  Exception
 */
class Expressomail_Exception_SievePutScriptFail extends Expressomail_Exception
{
    /**
     * construct
     * 
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = 'Could not save script on Sieve server.', $_code = 931)
    {
        parent::__construct($_message, $_code);
    }
}
