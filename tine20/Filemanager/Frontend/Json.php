<?php
/**
 * Tine 2.0
 *
 * @package     Filemanager
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * backend class for Zend_Json_Server
 *
 * This class handles all Json requests for the Filemanager application
 *
 * @package     Filemanager
 * @subpackage  Frontend
 */
class Filemanager_Frontend_Json extends Tinebase_Frontend_Json_Abstract
{
    /**
     * app name
     * 
     * @var string
     */
    protected $_applicationName = 'Filemanager';
    
    /**
     * search file/directory nodes
     *
     * @param  array $filter
     * @param  array $paging
     * @return array
     * 
     * @todo perhaps we can add searchCount() to the controller later and replace the count method TOTALCOUNT_COUNTRESULT
     */
    public function searchNodes($filter, $paging)
    {
        $result = $this->_search($filter, $paging, Filemanager_Controller_Node::getInstance(), 'Tinebase_Model_Tree_NodeFilter', FALSE, self::TOTALCOUNT_COUNTRESULT);
        
        return $result;
    }

    /**
     * create node(s)
     * 
     * @param string|array $filename
     * @param string|array $tempFileId
     * @return array
     * 
     * @todo implement
     */
    public function createNodes($filename, $tempFileId)
    {
        throw new Tinebase_Exception_NotImplemented('not implemented yet');
    }

    /**
     * copy node(s)
     * 
     * @param string|array $sourceFilenames string->single file, array->multiple
     * @param string|array $destinationFilenames string->singlefile OR directory, array->multiple files
     * @return array
     * 
     * @todo implement
     */
    public function copyNodes($sourceFilenames, $destinationFilenames)
    {
        throw new Tinebase_Exception_NotImplemented('not implemented yet');
    }

    /**
     * move node(s)
     * 
     * @param string|array $sourceFilenames string->single file, array->multiple
     * @param string|array $destinationFilenames string->singlefile OR directory, array->multiple files
     * @return array
     * 
     * @todo implement
     */
    public function moveNodes($sourceFilenames, $destinationFilenames)
    {
        throw new Tinebase_Exception_NotImplemented('not implemented yet');
    }

    /**
     * delete node(s)
     * 
     * @param string|array $filenames string->single file, array->multiple
     * @return array
     * 
     * @todo implement
     */
    public function deleteNodes($filenames)
    {
        throw new Tinebase_Exception_NotImplemented('not implemented yet');
    }
}
