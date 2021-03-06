<?php
/**
 * Expresso Lite
 * Handler for joinTempFiles calls.
 * Originally avaible in Tine.class (prior to the backend refactoring).
 *
 * @package   ExpressoLite\Backend
 * @license   http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author    Rodrigo Dias <rodrigo.dias@serpro.gov.br>
 * @author    Charles Wust <charles.wust@serpro.gov.br>
 * @copyright Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 */
namespace ExpressoLite\Backend\Request;

class JoinTempFiles extends LiteRequest
{

    /**
     * @see ExpressoLite\Backend\Request\LiteRequest::execute
     */
    public function execute()
    {
        $tempFilesData = json_decode($this->param('tempFiles'));

        $response = $this->jsonRpc('Tinebase.joinTempFiles', (object) array(
            'tempFilesData' => $tempFilesData
        ));

        return $response->result;
    }
}
