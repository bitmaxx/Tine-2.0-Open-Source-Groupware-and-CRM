<?php
/**
 * Tine 2.0
 * 
 * @package     Sipgate
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @author      Alexander Stintzing <alex@stintzing.net>
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id: Backend.php 2 2011-04-26 17:27:39Z alex $
 *
 */

/**
 * Backend exception
 * 
 * @package     Sipgate
 * @subpackage  Exception
 */
class Sipgate_Exception_ResolveCredentials extends Sipgate_Exception
{
    /**
     * construct
     * 
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = 'Could not resolve account settings.', $_code = 953) {
        // _('Could not resolve account settings.')
        parent::__construct($_message, $_code);
    }
}

?>