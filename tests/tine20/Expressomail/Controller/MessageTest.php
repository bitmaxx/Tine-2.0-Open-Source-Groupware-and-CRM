<?php
/**
 * Tine 2.0 - http://www.tine20.org
 *
 * @package     Expressomail
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2009-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 *
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Expressomail_Controller
 */
class Expressomail_Controller_MessageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Felamimail_Controller_Message
     */
    protected $_controller = NULL;
    
    /**
     * @var Felamimail_Model_Account
     */
    protected $_account = NULL;
    
    /**
     * keep track of created messages
     *
     * @var Tinebase_Record_RecordSet
     */
    protected $_createdMessages;
    
    /**
     * @var Felamimail_Backend_Imap
     */
    protected $_imap = NULL;
    
    /**
     * @var Felamimail_Model_Folder
     */
    protected $_folder = NULL;
    
    /**
     * name of the folder to use for tests
     * @var string
     */
    protected $_testFolderName = 'Junk';
    
    /**
     * accounts to delete in tearDown
     *
     * @var array
     */
    protected $_accountsToDelete = array();
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Expressomail Message Controller Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->_account    = Expressomail_Controller_Account::getInstance()->search()->getFirstRecord();
        $this->_controller = Expressomail_Controller_Message::getInstance();
        $this->_imap       = Expressomail_Backend_ImapFactory::factory($this->_account);
        if ($this->_testFolderName !== 'INBOX') {
            $this->_testFolderName = 'INBOX/' . $this->_testFolderName;
        }
        $this->_folder     = $this->getFolder($this->_testFolderName);
        $this->_imap->selectFolder($this->_testFolderName);
        $this->_createdMessages = new Tinebase_Record_RecordSet('Expressomail_Model_Message');
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        try {
            Expressomail_Controller_Message_Flags::getInstance()->addFlags($this->_createdMessages, array(Zend_Mail_Storage::FLAG_DELETED));
        } catch (Zend_Mail_Storage_Exception $zmse) {
            // do nothing
        }
        
        foreach ($this->_accountsToDelete as $account) {
            Expressomail_Controller_Account::getInstance()->delete($account);
        }
    }

    /********************************* test funcs *************************************/
    
    /**
     * test getting multiple messages
     */
    public function testGetMultipleMessages()
    {
        $message1 = $this->messageTestHelper('multipart_related.eml', 'multipart/related');
        $message2 = $this->messageTestHelper('text_plain.eml', 'text/plain');
        
        $messages = $this->_controller->getMultiple(array(
            $message1->getId(),
            $message2->getId()
        ));
        
        $this->assertEquals(2, count($messages));
    }
    
    /**
     * test search with cache
     * - test text_plain.eml message
     * - test from header
     */
    public function testSearchWithCache()
    {
        // get inbox folder id
        Expressomail_Controller_Folder::getInstance()->update($this->_account->getId());
        $folderBackend = new Expressomail_Backend_Folder();
        $folder = Expressomail_Controller_Folder::getInstance()->getByBackendAndGlobalName($this->_account->getId(), $this->_testFolderName);
        
        // clear empty folder
        Expressomail_Controller_Folder::getInstance()->emptyFolder($folder->getId());
        
        // append message
        $this->_appendMessage('text_plain.eml', $this->_folder);
        
        // search messages in test folder
        $result = $this->_controller->search($this->_getFilter($folder->getId()));
        
        //print_r($result->toArray());
        
        // check result
        $firstMessage = $result->getFirstRecord();
        $this->_createdMessages->addRecord($firstMessage);

        $this->assertGreaterThan(0, count($result));
        $this->assertEquals($folder->getId(), $firstMessage->folder_id);
        $this->assertEquals("Re: [gentoo-dev] `paludis --info' is not like `emerge --info'", $firstMessage->subject);
        $this->assertEquals('Pipping, Sebastian (Luxembourg)', $firstMessage->from_name);
        $this->assertEquals('webmaster@changchung.org', $firstMessage->from_email);
        $this->assertEquals(array('gentoo-dev@lists.gentoo.org', 'webmaster@changchung.org') , $firstMessage->to);
    }
    
    /**
     * testBodyStructureTextPlain
     */
    public function testBodyStructureTextPlain()
    {
        $expectedStructure = array(
            'partId'      => 1,
            'contentType' => 'text/plain',
            'type'        => 'text',
            'subType'     => 'plain',
            'parameters'  => array (
                'charset' => 'ISO-8859-1'
            ),
            'id'          => '',
            'description' => '',
            'encoding'    => '7bit',
            'size'        => 388,
            'disposition' => '',
            'language'    => '',
            'location'    => '',
            
        );

        $message = $this->messageTestHelper('text_plain.eml', 'text/plain');
        
        $lines = $message['structure']['lines'];
        $structure = $message['structure'];
        unset($structure['lines']);
        
        $this->assertEquals($expectedStructure, $structure, 'structure does not match');
        // dbmail always has one more line than dovecot
        $this->assertTrue(in_array($lines, array(17, 18)));
    }
    
    /**
     * testBodyStructureMultipartAlternative
     */
    public function testBodyStructureMultipartAlternative()
    {
        $expectedStructure = array(
            'partId'      => null,
            'contentType' => 'multipart/alternative',
            'type'        => 'multipart',
            'subType'     => 'alternative',
            'parts'       => array(
                1 => array(
                    'partId'      => 1,
                    'contentType' => 'text/plain',
                    'type'        => 'text',
                    'subType'     => 'plain',
                    'parameters'  => array (
                        'charset' => 'iso-8859-1'
                    ),
                    'id'          => '',
                    'description' => '',
                    'encoding'    => 'quoted-printable',
                    'size'        => 1726,
                    'disposition' => '',
                    'language'    => '',
                    'location'    => '',
                ),
                2 => array(
                    'partId'      => 2,
                    'contentType' => 'text/html',
                    'type'        => 'text',
                    'subType'     => 'html',
                    'parameters'  => array (
                        'charset' => 'iso-8859-1'
                    ),
                    'id'          => '',
                    'description' => '',
                    'encoding'    => 'quoted-printable',
                    'size'        => 10713,
                    'disposition' => '',
                    'language'    => '',
                    'location'    => '',
                )
            ),
            'parameters'  => array (
                'boundary' => '=_m192h4woyec67braywzx'
            ),
            'disposition' => '',
            'language'    => '',
            'location'    => '',
            
        );
        
        $message = $this->messageTestHelper('multipart_alternative.eml', 'multipart/alternative');
        $structure = $message['structure'];
        $lines = $this->_getLinesFromPartsAndRemoveFromStructure($structure);
        
        $this->assertEquals($expectedStructure, $structure, 'structure does not match');
        $this->assertTrue(in_array($lines[1], array(49, 50)));
        $this->assertTrue(in_array($lines[2], array(172, 173)));
    }
    
    /**
     * get lines from structure parts and remove them from structure array
     *
     * @param array $_structure
     * @return array
     */
    protected function _getLinesFromPartsAndRemoveFromStructure(&$_structure)
    {
        $lines = array();
        foreach ($_structure['parts'] as $key => $part) {
            $lines[$key] = $part['lines'];
            unset($_structure['parts'][$key]['lines']);
        }
        
        return $lines;
    }
    
    /**
     * testBodyStructureMultipartMixed
     */
    public function testBodyStructureMultipartMixed()
    {
        $expectedStructure = array(
            'partId'      => null,
            'contentType' => 'multipart/mixed',
            'type'        => 'multipart',
            'subType'     => 'mixed',
            'parts'       => array(
                1 => array(
                    'partId'      => 1,
                    'contentType' => Expressomail_Model_Message::CONTENT_TYPE_PLAIN,
                    'type'        => 'text',
                    'subType'     => 'plain',
                    'parameters'  => array (
                        'charset' => 'us-ascii'
                    ),
                    'id'          => null,
                    'description' => null,
                    'encoding'    => '7bit',
                    'size'        => 3896,
                    'disposition' => array(
                        'type'    => 'inline'
                    ),
                    'language'    => '',
                    'location'    => '',
                ),
                2 => array(
                    'partId'      => 2,
                    'contentType' => Expressomail_Model_Message::CONTENT_TYPE_PLAIN,
                    'type'        => 'text',
                    'subType'     => 'plain',
                    'parameters'  => array (
                        'charset' => 'us-ascii'
                    ),
                    'id'          => '',
                    'description' => '',
                    'encoding'    => '7bit',
                    'size'        => 2787,
                    'disposition' => array(
                        'type'    => 'attachment',
                    ),
                    'language'    => '',
                    'location'    => '',
                )
            ),
            'parameters'  => array (
                'boundary' => '0F1p//8PRICkK4MWrobbat28989323553773'
            ),
            'disposition' => array(
                'type'    => 'inline'
            ),
            'language'    => '',
            'location'    => '',
        );
        
        $expectedParameters = array(
            'foobar'   => 'Test Subjäct',
            'filename' => 'add-removals.1239580800.log'
        );
        
        $message = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed');
        $structure = $message['structure'];
        $lines = $this->_getLinesFromPartsAndRemoveFromStructure($structure);
        // attachment parameters could have different order
        $parameters = $structure['parts'][2]['disposition']['parameters'];
        unset($structure['parts'][2]['disposition']['parameters']);
        
        $this->assertEquals($expectedStructure, $structure, 'structure does not match');
        $this->assertEquals(Expressomail_Model_Message::CONTENT_TYPE_PLAIN, $message['body_content_type']);
        $this->assertTrue(in_array($lines[1], array(61, 62)));
        $this->assertTrue(in_array($lines[2], array(52, 53)));
        $this->assertTrue($expectedParameters == $parameters);
    }
    
    /**
     * testBodyStructureMultipartMixedWithMessageRFC822
     */
    public function testBodyStructureMultipartMixedWithMessageRFC822()
    {
        $expectedStructure = array(
            'partId'      => null,
            'contentType' => 'multipart/mixed',
            'type'        => 'multipart',
            'subType'     => 'mixed',
            'parts'       => array(
                1 => array(
                    'partId'      => 1,
                    'contentType' => 'text/plain',
                    'type'        => 'text',
                    'subType'     => 'plain',
                    'parameters'  => array (
                        'charset' => 'ISO-8859-1',
                        'format'  => 'flowed'
                    ),
                    'id'          => null,
                    'description' => null,
                    'encoding'    => '7bit',
                    'size'        => 49,
                    'disposition' => null,
                    'language'    => '',
                    'location'    => '',
                ),
                2 => array(
                    'partId'      => 2,
                    'contentType' => 'message/rfc822',
                    'type'        => 'message',
                    'subType'     => 'rfc822',
                    'parameters'  => array (
                        'name'    => '[Officespot-cs-svn] r15209 - trunk/tine20/Tinebase.eml'
                    ),
                    'id'          => '',
                    'description' => '',
                    'encoding'    => '7bit',
                    'size'        => 4121,
                    'disposition' => null,
                    'language'    => null,
                    'location'    => null,
                    'messageEnvelop' => array(
                        'Wed, 30 Jun 2010 13:20:09 +0200',
                        '[Officespot-cs-svn] r15209 - trunk/tine20/Tinebase',
                        array(array(
                            'NIL', 'NIL', 'c.weiss', 'metaways.de'
                        )),
                        array(array(
                            'NIL', 'NIL', 'c.weiss', 'metaways.de'
                        )),
                        array(array(
                            'NIL', 'NIL', 'c.weiss', 'metaways.de'
                        )),
                        array(array(
                            'NIL', 'NIL', 'officespot-cs-svn', 'lists.sourceforge.net'
                        )),
                        'NIL',
                        'NIL',
                        'NIL',
                        '<20100630112010.06CD21C059@publicsvn.hsn.metaways.net>'
                    ),
                    'messageStructure' => array(
                        'partId'  => 2,
                        'contentType' => 'text/plain',
                        'type'        => 'text',
                        'subType'     => 'plain',
                        'parameters'  => array (
                            'charset' => 'us-ascii'
                        ),
                        'id'          => null,
                        'description' => null,
                        'encoding'    => '7bit',
                        'size'        => 1562,
                        'disposition' => null,
                        'language'    => '',
                        'location'    => '',
                    ),
                )
            ),
            'parameters'  => array (
                'boundary' => '------------040506070905080909080505'
            ),
            'disposition' => null,
            'language'    => '',
            'location'    => '',
        );
        
        $message = $this->messageTestHelper('multipart_rfc2822.eml', 'multipart/rfc2822');
        $structure = $message['structure'];
        $lines = $this->_getLinesFromPartsAndRemoveFromStructure($structure);
        $lines[3] = $structure['parts'][2]['messageStructure']['lines'];
        $lines[4] = $structure['parts'][2]['messageLines'];
        unset($structure['parts'][2]['messageStructure']['lines']);
        unset($structure['parts'][2]['messageLines']);
        // remove disposition -> dbmail finds none, dovecot does
        $structure['parts'][2]['disposition'] = null;
        
        $this->assertEquals($expectedStructure, $structure, 'structure does not match');
        $this->assertTrue(in_array($lines[1], array(4, 5)));
        $this->assertEquals(NULL, $lines[2]);
        $this->assertTrue(in_array($lines[3], array(33, 34)));
        $this->assertTrue(in_array($lines[4], array(80, 81)));
    }
    
    /**
     * testGetBodyMultipartRelated
     */
    public function testGetBodyMultipartRelated()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');

        $body = $this->_controller->getMessageBody($cachedMessage, null, Zend_Mime::TYPE_TEXT, $this->_account);
        
        $this->assertContains('würde', $body);
    }
    
    /**
     * test reading a message without setting the \Seen flag
     */
    public function testGetBodyMultipartRelatedReadOnly()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');

        $body = $this->_controller->getMessageBody($cachedMessage, null, Zend_Mime::TYPE_TEXT, $this->_account, true);
        
        $this->assertContains('würde', $body);
        
        // @todo check for seen flag
    }
    
    /**
     * testGetBodyPlainText
     */
    public function testGetBodyPlainText()
    {
        $cachedMessage = $this->messageTestHelper('text_plain.eml', 'text/plain');
        
        $body = $this->_controller->getMessageBody($cachedMessage, null, Zend_Mime::TYPE_TEXT, $this->_account);
        
        $this->assertContains('a converter script be written to', $body);
    }
    
    /**
     * testGetBodyPart
     */
    public function testGetBodyPart()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');
        
        $part = $this->_controller->getMessagePart($cachedMessage, '2');
        
        $this->assertContains(Zend_Mime::MULTIPART_RELATED, $part->type);
        $this->assertContains("------------080303000508040404000908", $part->boundary);
        
        $part = $this->_controller->getMessagePart($cachedMessage, '2.1');
        
        $this->assertContains(Zend_Mime::TYPE_HTML, $part->type);
        $this->assertContains(Zend_Mime::ENCODING_QUOTEDPRINTABLE, $part->encoding);
        
        $part = $this->_controller->getMessagePart($cachedMessage, '2.2');
        
        $this->assertContains(Zend_Mime::DISPOSITION_ATTACHMENT, $part->disposition);
        $this->assertContains(Zend_Mime::ENCODING_BASE64, $part->encoding);
    }
    
    /**
     * testGetCompleteMessageAsPart
     */
    public function testGetCompleteMessageAsPart()
    {
        $cachedMessage = $this->messageTestHelper('complete.eml', 'text/service');
        
        $messagePart = $this->_controller->getMessagePart($cachedMessage);
        
        ob_start();
        fpassthru($messagePart->getRawStream());
        $out = ob_get_clean();
        
        $this->assertContains('URL: https://service.metaways.net/Ticket/Display.html?id=3D59648', $out);
    }
        
    /**
     * testGetMessagePartRfc822
     */
    public function testGetMessagePartRfc822()
    {
        $cachedMessage = $this->messageTestHelper('multipart_rfc2822-2.eml', 'multipart/rfc2822-2');
        
        $messagePart = $this->_controller->getMessagePart($cachedMessage, 2);
        
        ob_start();
        fpassthru($messagePart->getRawStream());
        $out = ob_get_clean();
        
        $this->assertContains('X-AntiAbuse: Originator/Caller UID/GID - [47 12] / [47 12]', $out, 'header not found');
        $this->assertContains('This component, from the feedback I have, will mostly be used on', $out, 'body not found');
    }
    
    /**
     * validate fetching a complete message
     */
    public function testGetCompleteMessage()
    {
        $cachedMessage = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertEquals('robbat2@gentoo.org', $message->from_email);
        $this->assertEquals($this->_account->getId(), $message->account_id);
        $this->assertEquals('Robin H. Johnson', $message->from_name);
        $this->assertEquals('"Robin H. Johnson" <robbat2@stork.gentoo.org>', $message->sender);
        $this->assertEquals('1', $message->text_partid);
        $this->assertEquals('1', $message->has_attachment);
        $this->assertEquals(null, $message->html_partid);
        $this->assertEquals('9606', $message->size);
        $this->assertContains("Automated Package Removal", $message->subject);
        $this->assertContains('\Seen', $message->flags);
        $this->assertContains('11AC BA4F 4778 E3F6 E4ED  F38E B27B 944E 3488 4E85', $message->body);
        $this->assertEquals('add-removals.1239580800.log', $message->attachments[0]["filename"]);
    }

    /**
     * validate fetching a complete message in 'other' dir and check its body
     *
     * howto:
     * - copy mails to tests/tine20/Felamimail/files/other
     * - add following header:
     *      X-Tine20TestMessage: _filename_
     * - run the test!
     */
    public function testCheckOtherMails()
    {
        $otherFilesDir = dirname(dirname(__FILE__)) . '/files/other';
        if (file_exists($otherFilesDir)) {
            foreach (new DirectoryIterator($otherFilesDir) as $item) {
                $filename = $item->getFileName();
                if ($item->isFile() && $filename !== 'README') {
                    $fileName = 'other/' . $filename;
                    echo "\nchecking message: " . $fileName . "\n";
                    $cachedMessage = $this->messageTestHelper($fileName, $filename);
                    $message = $this->_controller->getCompleteMessage($cachedMessage);
                    echo $message->body;
                    $this->assertTrue(! empty($message->body));
                }
            }
        }
    }
    
    /**
     * validate fetching a complete message
     */
    public function testGetCompleteMessage2()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertEquals('1', $message->text_partid, 'no text part found');
        $this->assertEquals('1', $message->has_attachment, 'no attachments found');
        $this->assertEquals('2.1', $message->html_partid, 'no html part found');
        $this->assertTrue(in_array($message->size, array('38455', '38506')));
        $this->assertContains("Tine 2.0 bei Metaways", $message->subject);
        $this->assertContains('\Seen', $message->flags);
        $this->assertContains('Autovervollständigung', $message->body);
        $this->assertEquals('moz-screenshot-83.png', $message->attachments[0]["filename"]);
    }
    
    /**
     * validate fetching a complete message
     */
    public function testGetCompleteMessage3()
    {
        $cachedMessage = $this->messageTestHelper('multipart_rfc2822.eml', 'multipart/rfc2822');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertEquals('multipart/mixed', $message->content_type);
        $this->assertEquals('5377', $message->size);
        $this->assertContains("Fwd: [Officespot-cs-svn] r15209 - trunk/tine20/Tinebase", $message->subject);
        $this->assertContains('est for parsing forwarded email', $message->body);
        $this->assertEquals('message/rfc822', $message->attachments[0]["content-type"]);
    }

    /**
     * validate fetching a complete message from amazon
     */
    public function testGetCompleteMessageAmazon()
    {
        $cachedMessage = $this->messageTestHelper('Amazon.eml', 'multipart/amazon');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertEquals('multipart/alternative', $message->content_type);
        $this->assertContains('Samsung Wave S8500 Smartphone', $message->subject);
        $this->assertContains('Sie suchen Produkte aus der Kategorie Elektronik &amp; Foto?', $message->body);
    }
    
    /**
     * validate fetching a message from yahoo
     *
     * test was created for task #4680
     */
    public function testGetCompleteMessageYahoo()
    {
        $cachedMessage = $this->messageTestHelper('yahoo.eml');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertContains('Bitte aktualisieren Sie Ihre Kontoeinstellungen bzw. Daten-Feeds so schnell wie möglich', $message->body);
    }
    
    /**
     * validate fetching a complete message from amazon #2 -> check if images got removed correctly
     */
    public function testGetCompleteMessageAmazon2()
    {
        $cachedMessage = $this->messageTestHelper('Amazon2.eml', 'multipart/amazon2');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertContains('Fritz Meier, wir haben Empfehlungen', $message->body);
        $this->assertNotContains('<img', $message->body);
        $this->assertNotContains('style="background-image:url', $message->body);
        $this->assertNotContains('http://www.xing.com/img/xing/newsletter/navigation_bg.gif', $message->body);
    }
    
    /**
     * validate fetching a complete message from order form
     */
    public function testGetCompleteMessageOrder()
    {
        $cachedMessage = $this->messageTestHelper('Angebotsformular.eml', 'text/angebot');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertEquals('text/plain', $message->content_type);
        $this->assertContains('Angebotsformular', $message->subject);
        $this->assertContains('*Formular-Weiterleitungs-Service*', $message->body);
    }

    /**
     * validate fetching a complete message with different encodings
     */
    public function testGetCompleteMessageDifferentEncoding()
    {
        $cachedMessage = $this->messageTestHelper('UmlauteUTF8TextISO-8859-15Signatur.eml', 'text/different');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        //print_r($message->toArray());
        $this->assertEquals('text/plain', $message->content_type);
        $this->assertContains('Umlaute UTF8 Text + ISO-8859-15 Signatur', $message->subject);
        $this->assertContains('O Ö', $message->body);
    }
    
    /**
     * validate fetching a complete message (rfc2822 part)
     */
    public function testGetMessageRFC822()
    {
        $cachedMessage = $this->messageTestHelper('multipart_rfc2822.eml', 'multipart/rfc2822');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage, 2);
        
        $this->assertEquals('4121', $message->size);
        $this->assertContains("[Officespot-cs-svn] r15209 - trunk/tine20/Tinebase", $message->subject);
        $this->assertTrue(isset($message->body), 'no body found');
        $this->assertContains('getLogger()-&gt;debug', $message->body);
    }
    
    /**
     * validate fetching a complete message
     */
    public function testGetMessageRFC822_2()
    {
        $cachedMessage = $this->messageTestHelper('multipart_rfc2822-2.eml', 'multipart/rfc2822-2');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage, 2);
        
        $this->assertEquals('19131', $message->size);
        $this->assertContains("Proposal: Zend_Grid", $message->subject);
        $this->assertTrue(isset($message->body), 'no body found');
        $this->assertContains('Bento Vilas Boas wrote', $message->body ,'string not found in body: ' . $message->body);
        $this->assertEquals('smime.p7s', $message->attachments[0]["filename"]);
    }
    
    /**
     * validate fetching a complete message / rfc822 with base64
     */
    public function testGetMessageRFC822_3()
    {
        $cachedMessage = $this->messageTestHelper('multipart_rfc2822-3.eml', 'multipart/rfc2822-3');
        
        $message = $this->_controller->getCompleteMessage($cachedMessage, 2);
        
        $this->assertTrue(isset($message->body), 'no body found');
        $this->assertContains('this is base64 encoded', $message->body ,'string not found in body: ' . $message->body);
    }
    
    /**
     * test adding message with duplicate to: header
     */
    public function testAddMessageToCacheDuplicateTo()
    {
        $cachedMessage = $this->messageTestHelper('text_plain2.eml', 'text_plain2.eml');
        
        $this->assertGreaterThan(0, count($cachedMessage->to));
        $this->assertContains('c.weiss@metaways.de', $cachedMessage->to[0], 'wrong "to" header:' . print_r($cachedMessage->to, TRUE));
        $this->assertContains('online', $cachedMessage->subject);
    }
    
    /**
     * test adding message with invalid date
     */
    public function testAddMessageToCacheInvalidDate()
    {
        $cachedMessage = $this->messageTestHelper('invaliddate.eml', 'text/invaliddate');
        
        $this->assertEquals('2010-03-01 21:39:42', $cachedMessage->sent->toString());
    }
    
    /**
     * test adding message with another invalid date
     */
    public function testAddMessageToCacheInvalidDate2()
    {
        $cachedMessage = $this->messageTestHelper('invaliddate2.eml', 'text/invaliddate2');
        
        $this->assertEquals('2009-03-16 19:51:23', $cachedMessage->sent->toString());
    }
    
    /**
     * test adding message with empty date header
     */
    public function testAddMessageToCacheEmptyDate()
    {
        $cachedMessage = $this->messageTestHelper('empty_date_header.eml', 'empty_date_header.eml');
        
        $this->assertEquals(0, $cachedMessage->sent->getTimestamp(), 'no timestamp should be set');
    }
    
    /**
     * test forward with attachment
     */
    public function testForwardMessageWithAttachment()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');
        
        $forwardMessage = new Expressomail_Model_Message(array(
            'account_id'    => $this->_account->getId(),
            'subject'       => 'test forward',
            'to'            => array($this->getEmailAddress()),
            'body'          => 'aaaaaä <br>',
            'headers'       => array('X-Tine20TestMessage' => Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822),
            'original_id'   => $cachedMessage->getId(),
            'attachments'   => array(new Tinebase_Model_TempFile(array(
                'type'  => Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822,
                'name'  => $cachedMessage->subject,
            ), TRUE)),
        ));
        $sentFolder = $this->getFolder('Sent');

        Expressomail_Controller_Message_Send::getInstance()->sendMessage($forwardMessage);
        
        $forwardedMessage = $this->searchAndCacheMessage(Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822, $this->getFolder('INBOX'));
        $forwardedMessageInSent = $this->searchAndCacheMessage(Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822, $sentFolder);
        $completeForwardedMessage = $this->_controller->getCompleteMessage($forwardedMessage);
        
        $this->assertEquals(Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822, $forwardedMessage['structure']['parts'][2]['contentType']);
        $this->assertEquals($cachedMessage->subject . '.eml', $forwardedMessage['structure']['parts'][2]['parameters']['name'],
            'filename mismatch in structure' . print_r($forwardedMessage['structure']['parts'][2], TRUE));
        $this->assertEquals($cachedMessage->subject . '.eml', $completeForwardedMessage->attachments[0]['filename'],
            'filename mismatch of attachment' . print_r($completeForwardedMessage->attachments[0], TRUE));
        
        return $forwardedMessage;
    }
    
    /**
     * get email address
     *
     * @return string
     */
    public function getEmailAddress()
    {
        $config = TestServer::getInstance()->getConfig();
        $email = ($config->email) ? $config->email : Tinebase_Core::getUser()->accountEmailAddress;
        
        return $email;
    }

    /**
     * test forward message part
     */
    public function testForwardMessagePart()
    {
        $forwardedMessage = $this->testForwardMessageWithAttachment();
        
        $forwardMessage = new Expressomail_Model_Message(array(
            'account_id'    => $this->_account->getId(),
            'subject'       => 'test forward part',
            'to'            => array($this->getEmailAddress()),
            'body'          => 'aaaaaä <br>',
            'headers'       => array('X-Tine20TestMessage' => Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822 . 'part'),
            'original_id'   => $forwardedMessage->getId() . '_2', // part 2 is the original forwared message
            'attachments'   => array(new Tinebase_Model_TempFile(array(
                'type'  => Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822,
                'name'  => $forwardedMessage->subject,
            ), TRUE)),
        ));
        Expressomail_Controller_Message_Send::getInstance()->sendMessage($forwardMessage);
        
        $forwardedMessage = $this->searchAndCacheMessage(Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822 . 'part', $this->getFolder('INBOX'));
        $completeForwardedMessagePart = $this->_controller->getCompleteMessage($forwardedMessage, 2);
        
        //print_r($completeForwardedMessagePart->toArray());
        $this->assertTrue(! empty($completeForwardedMessagePart->headers), 'headers should not be empty');
        $this->assertEquals('moz-screenshot-83.png', $completeForwardedMessagePart->attachments[0]['filename']);
    }
    
    /**
     * testGetBodyPartIdMultipartAlternative
     */
    public function testGetBodyPartIdMultipartAlternative()
    {
        $cachedMessage = $this->messageTestHelper('multipart_alternative.eml', 'multipart/alternative');
        $cachedMessage->parseBodyParts();

        $this->assertEquals(1, $cachedMessage->text_partid, 'did not find all partIds');
        $this->assertEquals(2, $cachedMessage->html_partid, 'did not find all partIds');
    }
        
    /**
     * testGetBodyPartIdMultipartMixed
     */
    public function testGetBodyPartIdMultipartMixed()
    {
        $cachedMessage = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed');
        $cachedMessage->parseBodyParts();

        $this->assertEquals(1, $cachedMessage->text_partid, 'did not find all partIds');
    }
    
    /**
     * testGetBodyPartIdMultipartSigned
     */
    public function testGetBodyPartIdMultipartSigned()
    {
        $cachedMessage = $this->messageTestHelper('multipart_signed.eml', 'multipart/signed');
        $cachedMessage->parseBodyParts();

        $this->assertEquals(1, $cachedMessage->text_partid, 'did not find all partIds');
    }
    
    /**
     * testGetBodyPartIdMultipartRelated
     */
    public function testGetBodyPartIdMultipartRelated()
    {
        $cachedMessage = $this->messageTestHelper('multipart_related.eml', 'multipart/related');
        $cachedMessage->parseBodyParts();

        $this->assertEquals(1, $cachedMessage->text_partid, 'did not find all partIds');
        $this->assertEquals('2.1', $cachedMessage->html_partid, 'did not find all partIds');
    }

    /**
     * testGetMessageWithoutFromHeader
     */
    public function testGetMessageWithoutFromHeader()
    {
        $cachedMessage = $this->messageTestHelper('withoutfrom.eml', 'text/withoutfrom');
        $completeMessage = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertContains('Hier ist Ihr Hot Web Email-Deal Angebot von M&amp;M Computer.', $completeMessage->body);
    }
    
    /**
     * testGetMessageWithCommaInTo
     */
    public function testGetMessageWithCommaInTo()
    {
        $cachedMessage = $this->messageTestHelper('mail_to_comma.eml', 'text/comma');
        $completeMessage = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertEquals('inscription@arrakeen.net', $completeMessage->to[0]);
        $this->assertEquals('November 2010 Crystal Newsletter - Cut the Rope Update Released!', $completeMessage->subject);
    }
    
    /**
     * testUnparseableMail
     */
    public function testUnparseableMail()
    {
        $cachedMessage = $this->messageTestHelper('unparseable.eml', 'multipart/unparseable');
        $completeMessage = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertEquals(1, preg_match('@NIL|Content-Type: image/jpeg@', $completeMessage->body), 'parsed mail body:' . $completeMessage->body);
    }
    
    /**
     * test utf8 header decode
     */
    public function testUtf8HeaderDecode()
    {
        $cachedMessage = $this->messageTestHelper('decode_utf8_header.eml');
        $completeMessage = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertEquals('"Jörn Meier" <j.meier@test.local>', $completeMessage->headers['reply-to']);
        $this->assertEquals('Jörn Meier <j.meier@test.local>', $completeMessage->headers['from']);
        $this->assertEquals('j.meier@test.local', $completeMessage->to[0]);
    }
    
    /**
     * testLongFrom
     */
    public function testLongFrom()
    {
        $cachedMessage = $this->messageTestHelper('longfrom.eml');
        $this->assertEquals('nDqIxSoSTIC', $cachedMessage->subject);
    }
        
    /**
     * testGetMessageWithQuotedPrintableDecodeProblem
     */
    public function testGetMessageWithQuotedPrintableDecodeProblem()
    {
        $cachedMessage = $this->messageTestHelper('Terminbestaetigung.eml', 'Terminbestaetigung.eml');
        $completeMessage = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertContains('Veröffentlichungen, Prospekte und Ähnliches bereithalten würden.', $completeMessage->body);
    }
    
    /**
     * test move to another account
     */
    public function testMoveMessageToAnotherAccount()
    {
        $clonedAccount = $this->_cloneAccount();
        $folder = $this->getFolder('INBOX', $clonedAccount);
        
        $cachedMessage = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed');
        $this->_moveTestHelper($cachedMessage, $folder);
    }
    
    /**
     * test move to another account (with message filter)
     */
    public function testMoveMessageToAnotherAccountWithFilter()
    {
        $clonedAccount = $this->_cloneAccount();
        $folder = $this->getFolder('INBOX', $clonedAccount);
        
        $cachedMessage = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed');
        $messageFilter = new Expressomail_Model_MessageFilter(array(
            array('field' => 'id', 'operator' => 'in', 'value' => array($cachedMessage->getId()))
        ));
        
        $this->_moveTestHelper($messageFilter, $folder);
    }
    
    /**
     * move message test helper
     *
     * @param mixed $_toMove
     * @param Felamimail_Model_Folder $_folder
     */
    protected function _moveTestHelper($_toMove, $_folder)
    {
        Expressomail_Controller_Message_Move::getInstance()->moveMessages($_toMove, $_folder);
        $message = $this->_searchMessage('multipart/mixed', $_folder);
        
        $result = $this->_controller->search($this->_getFilter($folder->getId()));
        foreach ($result as $messageInCache) {
            if ($messageInCache->messageuid == $message['uid']) {
                $foundMessage = $messageInCache;
                break;
            }
        }
        
        $this->assertTrue(isset($foundMessage));
        $this->_createdMessages[] = $foundMessage;
        $completeMessage = $this->_controller->getCompleteMessage($foundMessage);
        $this->assertContains('The attached list notes all of the packages that were added or removed', $completeMessage->body);
    }
    
     /**
     * test delete in different accounts
     */
    public function testDeleteMessagesInDifferentAccounts()
    {
        $clonedAccount = $this->_cloneAccount();
        
        $trashFolderMainAccount = $this->getFolder('Trash');
        $trashFolderClonedAccount = $this->getFolder('Trash', $clonedAccount);
        
        // empty trash
        Expressomail_Controller_Folder::getInstance()->emptyFolder($trashFolderMainAccount);
        
        $cachedMessage1 = $this->messageTestHelper('multipart_mixed.eml', 'multipart/mixed', $trashFolderMainAccount);
        $cachedMessage2 = $this->messageTestHelper('complete.eml', 'text/service', $trashFolderClonedAccount);
        
        Expressomail_Controller_Message_Flags::getInstance()->addFlags(array($cachedMessage1->getId(), $cachedMessage2->getId()), array(Zend_Mail_Storage::FLAG_DELETED));
        
        $result1 = $this->_searchOnImap('multipart/mixed', $trashFolderMainAccount);
        $this->assertEquals(0, count($result1), $trashFolderMainAccount->globalname . ' still contains multipart/mixed messages:' . print_r($result1, TRUE));
        $result2 = $this->_searchOnImap('text/service', $trashFolderClonedAccount);
        $this->assertEquals(0, count($result2), $trashFolderClonedAccount->globalname . ' still contains text/service messages:' . print_r($result2, TRUE));
    }
    
    /**
     * test converting from punycode (xn--stermnn-9wa0n.org -> östermänn.org)
     */
    public function testPunycodedFromHeader()
    {
        $cachedMessage = $this->messageTestHelper('punycode_from.eml', 'punycode');
        $this->assertEquals('albert@östermänn.org', $cachedMessage->from_email);
    }

    /**
     * test converting to punycode
     */
    public function testEncodeToPunycode()
    {
        $message = new Expressomail_Model_Message(array(
            'to'        => array('albert@östermänn.org'),
            'subject'   => 'punycode test',
        ));
        $mail = Expressomail_Controller_Message_Send::getInstance()->createMailForSending($message, $this->_account);
        
        $recipients = $mail->getRecipients();
        $this->assertEquals('albert@xn--stermnn-9wa0n.org', $recipients[0]);
    }
    
    /**
     * test line end encoding of Zend_Mime_Part / Smtp Protocol
     */
    public function testSendWithWrongLineEnd()
    {
        $config = TestServer::getInstance()->getConfig();
        $mailDomain = ($config->maildomain) ? $config->maildomain : 'tine20.org';
        
        // build message with wrong line end rfc822 part
        $mail = new Tinebase_Mail('utf-8');
        $mail->setBodyText('testmail' . "\r\n" . "\r\n");
        $mail->setFrom('unittest@' . $mailDomain, 'unittest');
        $mail->setSubject('line end test');
        $mail->addTo('unittest@' . $mailDomain);
        $mail->addHeader('X-Tine20TestMessage', 'lineend');
        
        // replace EOLs
        $content = file_get_contents(dirname(dirname(__FILE__)) . '/files/text_plain.eml');
        $content = preg_replace("/\\x0a/", "\r\n", $content);
        $stream = fopen("php://temp", 'r+');
        fputs($stream, $content);
        rewind($stream);
        
        $attachment = new Zend_Mime_Part($stream);
        $attachment->type        = Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822;
        $attachment->encoding    =  null;
        $attachment->charset     = 'ISO-8859-1';
        $attachment->filename    = 'attach.eml';
        $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                
        $mail->addAttachment($attachment);
        
        $smtpConfig = $this->_account->getSmtpConfig();
        $transport = new Expressomail_Transport($smtpConfig['hostname'], $smtpConfig);
        $mail->send($transport);
        
        $smtpLog = $transport->getConnection()->getLog();
        
        $badLineEndCount = preg_match_all("/\\x0d\\x0d\\x0a/", $smtpLog, $matches);
        $this->assertEquals(0, $badLineEndCount);
        
        $badLineEndCount = preg_match_all("/\\x0d/", $smtpLog, $matches);
        $this->assertTrue(preg_match_all("/\\x0d/", $smtpLog, $matches) > 70, 'unix line ends are missing');
        
    }
    
   /**
    * validate email invitation
    */
    public function testEmailInvitation()
    {
        $email = $this->_getTestEmailAddress();
        $cachedMessage = $this->messageTestHelper('invitation.eml', NULL, NULL, array('unittest@tine20.org', $email));
        $this->_testInvitationMessage($cachedMessage, 'pwulf@tine20.org', 'testevent', 2);
    }
    
    /**
     * _testInvitationMessage
     * 
     * @param Felamimail_Model_Message $cachedMessage
     * @param string $expectedOriginator
     * @param string $expectedEventSummary
     * @param integer $expectedAttendeeCount
     */
    protected function _testInvitationMessage($cachedMessage, $expectedOriginator, $expectedEventSummary, $expectedAttendeeCount)
    {
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertEquals(1, count($message->preparedParts));
        $preparediMIPPart = $message->preparedParts->getFirstRecord()->preparedData;
        $this->assertTrue($preparediMIPPart instanceof Calendar_Model_iMIP, 'is no iMIP');
        $this->assertEquals($expectedOriginator, $preparediMIPPart->originator);
        $event = $preparediMIPPart->getEvent();
        $this->assertTrue($event instanceof Calendar_Model_Event, 'is no event');
        $this->assertEquals($expectedEventSummary, $event->summary);
        $this->assertEquals($expectedAttendeeCount, count($event->attendee));
    }

   /**
    * validate email invitation from mac
    */
    public function testEmailInvitationFromMac()
    {
        $cachedMessage = $this->messageTestHelper('mac_invitation.eml');
    
        $message = $this->_controller->getCompleteMessage($cachedMessage);
    
        $this->assertEquals(1, count($message->preparedParts));
        $preparediMIPPart = $message->preparedParts->getFirstRecord()->preparedData;
        $this->assertTrue($preparediMIPPart instanceof Calendar_Model_iMIP, 'is no iMIP');
        $this->assertEquals('pwulf@tine20.org', $preparediMIPPart->originator);
    }

   /**
    * validate email invitation from outlook
    * 
    * @see 0006110: handle iMIP messages from outlook
    */
    public function testEmailInvitationFromOutlook()
    {
        $email = $this->_getTestEmailAddress();
        $cachedMessage = $this->messageTestHelper('outlookimip.eml', NULL, NULL, array('name@example.net', $email));
        $this->_testInvitationMessage($cachedMessage, 'name@example.com', 'test', 1);
    }
    
   /**
    * validate email invitation from outlook (base64 encoded ics)
    * 
    * @see 0006110: handle iMIP messages from outlook
    */
    public function testEmailInvitationFromOutlookBase64()
    {
        $email = $this->_getTestEmailAddress();
        $cachedMessage = $this->messageTestHelper('invite_outlook.eml', NULL, NULL, array('oliver@example.org', $email));
        $this->_testInvitationMessage($cachedMessage, 'user@telekom.ch', 'Test von Outlook an Tine20', 1);
    }
    
    /**
     * get test email address
     * 
     * @return string
     */
    protected function _getTestEmailAddress()
    {
        $testConfig = Zend_Registry::get('testConfig');
        $email = ($testConfig->email) ? $testConfig->email : 'unittest@tine20.org';
        return $email;
    }
    
    /**
     * testFromUTF8Encoding
     * 
     * @see 0006538: charset problems with recipients/senders
     */
    public function testFromUTF8Encoding()
    {
        $cachedMessage = $this->messageTestHelper('UTF8inFrom.eml');
        $this->assertEquals('Philipp Schüle', $cachedMessage->from_name, print_r($cachedMessage->toArray(), TRUE));
    }
    
    /**
     * testHeaderWithoutEncodingInformation
     * 
     * @see 0006250: missing Umlauts in some mails
     */
    public function testHeaderWithoutEncodingInformation()
    {
        $cachedMessage = $this->messageTestHelper('Wortmann1.eml');
        
        $this->assertTrue(! empty($cachedMessage->subject) && is_string($cachedMessage->subject), 'subject empty or no string: '. print_r($cachedMessage->toArray(), TRUE));
        $this->assertContains('Höchstgeschwindigkeit', $cachedMessage->subject, print_r($cachedMessage->toArray(), TRUE));
    }
    
    /**
     * testFilterTooMuchHtml
     * 
     * @see 0007142: sometimes we filter to much html content
     */
    public function testFilterTooMuchHtml()
    {
        $cachedMessage = $this->messageTestHelper('heavyhtml.eml');
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertContains('unwahrscheinlichen Fall, dass Probleme auftreten sollten,', $message->body, print_r($message->toArray(), TRUE));
    }
    
    /**
     * testUmlautAttachment
     * 
     * @see 0007624: losing umlauts in attached filenames
     */
    public function testUmlautAttachment()
    {
        $cachedMessage = $this->messageTestHelper('attachmentUmlaut.eml');
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertEquals(1, count($message->attachments));
        $this->assertEquals('äöppopä.txt', $message->attachments[0]['filename']);
    }

    /**
     * testNewsletterMultipartRelated
     * 
     * @see 0007722: improve handling of newsletters
     */
    public function testNewsletterMultipartRelated()
    {
        $cachedMessage = $this->messageTestHelper('mw_newsletter_multipart_related.eml');
        $this->assertEquals(1, $cachedMessage->has_attachment);
        $bodyParts = $cachedMessage->getBodyParts();
        $this->assertEquals(Zend_Mime::TYPE_HTML, $bodyParts['2.1']['contentType'], 'multipart/related html part missing: ' . print_r($bodyParts, TRUE));
        
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertNotContains('----------------------------<br />TINE 2.0<br />-----------------------', $message->body, 'message body contains plain/text part');
        $this->assertContains('<p style="color:#999999;"><strong>Die Glühweinzeit hat bereits begonnen und kälter geworden ist es auch...</strong></p>', $message->body);
        $this->assertEquals(Zend_Mime::TYPE_HTML, $message->body_content_type);
    }

    /**
     * testNewsletterMultipartRelated
     * 
     * @see 0007858: could not parse structure of multipart/related msg
     */
    public function testMultipartRelatedAlternative()
    {
        $cachedMessage = $this->messageTestHelper('multipart_alternative_related.eml');
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        $this->assertContains('some body contentsome body contentsome body content', $message->body);
    }

    /**
     * testNoAttachement
     * 
     * @see 0008014: js client shows wrong attachment icon in grid
     */
    public function testNoAttachement()
    {
        $cachedMessage = $this->messageTestHelper('noattachment.eml');
        $this->assertEquals(0, $cachedMessage->has_attachment);
    }
    
    /**
     * testHtmlPurify
     * 
     * @see 0007726: show inline images of multipart/related message parts
     * 
     * @todo allow external resources
     * @todo remove $_SERVER stuff?
     */
    public function testHtmlPurify()
    {
//         $_SERVER['SERVER_NAME'] = 'localhost';
//         $_SERVER['REQUEST_URI'] = '/tine20';
        $cachedMessage = $this->messageTestHelper('text_html_urls.eml');
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
//         unset($_SERVER['SERVER_NAME']);
//         unset($_SERVER['REQUEST_URI']);
        
//         $this->assertContains('<div></div>
//     <img src="http://localhost/tine20/index.php?Felamimail.getResource&amp;uri=aHR0cDovL3d3dy50aW5lMjAub3JnL2ZpbGVhZG1pbi90ZW1wbGF0ZXMvaW1hZ2VzL3RpbmUyMC5wbmc=&amp;type=img" alt="tine20.png" /><img src="http://localhost/tine20.png" alt="tine20.png" />
    
//     <p>text</p>', $message->body);
        $this->assertContains('<div></div>
    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==" alt="w38GIAXDIBKE0DHxgljNBAAO 9TXL0Y4OHwAAAAB" />
    
    <p>text</p>', $message->body);
    }

    /**
     * testNewsletterMultipartRelated
     * 
     * @see 0007726: show inline images of multipart/related message parts
     * 
     * @todo implement
     */
    public function testNewsletterMultipartRelatedWithImages()
    {
        $this->markTestIncomplete('implement');
        $cachedMessage = $this->messageTestHelper('mw_newsletter_multipart_related.eml');
    }
    
    /**
     * testFacebookNotification
     * 
     * @see 0008016: links in facebook/twitter emails are removed
     */
    public function testFacebookNotification()
    {
        $cachedMessage = $this->messageTestHelper('facebook_notification.eml');
        $message = $this->_controller->getCompleteMessage($cachedMessage);
        
        $this->assertContains('http://www.facebook.com/n/?notifications&amp;id=295475095891&amp;'
            . 'mid=7a0ffadG5af33a8a9c98Ga61c449Gdd&amp;bcode=1.1362559617.Abl6w95TdWQc0VVS&amp;n_m=tine20%40metaways.de', $message->body);
    }
    
    /********************************* protected helper funcs *************************************/
    
    /**
     * clones the account
     *
     * @return Felamimail_Model_Account
     */
    protected function _cloneAccount()
    {
        $account = clone($this->_account);
        unset($account->id);
        $this->_accountsToDelete[] = $account;
        $account = Expressomail_Controller_Account::getInstance()->create($account);
        
        return $account;
    }
    
    /**
     * helper function
     * - appends message from file
     * - adds appended message to cache
     *
     * @param string $_filename
     * @param string $_testHeaderValue
     * @param Felamimail_Model_Folder $_folder
     * @param array $_replacements
     * @return Felamimail_Model_Message
     */
    public function messageTestHelper($_filename, $_testHeaderValue = NULL, $_folder = NULL, $_replacements = array())
    {
        $testHeaderValue = ($_testHeaderValue !== NULL) ? $_testHeaderValue : $_filename;
        $folder = ($_folder !== NULL) ? $_folder : $this->_folder;
        $this->_appendMessage($_filename, $folder, $_replacements);
        return $this->searchAndCacheMessage($testHeaderValue, $folder);
    }
    
    /**
     * search message in folder
     *
     * @param string $_testHeaderValue
     * @param Felamimail_Model_Folder $_folder
     * @param boolean $_assert
     * @return array|NULL
     */
    protected function _searchMessage($_testHeaderValue, $_folder, $_assert = TRUE)
    {
        $imap = $this->_getImapFromFolder($_folder);

        $count = 0;
        do {
            sleep(1);
            $result = $this->_searchOnImap($_testHeaderValue, $_folder, $imap);
        } while (count($result) === 0 && $count++ < 5);
        
        if ($_assert) {
            $this->assertGreaterThan(0, count($result), 'No messages with HEADER X-Tine20TestMessage: ' . $_testHeaderValue . ' in folder ' . $_folder->globalname . ' found.');
        }
        $message = (! empty($result)) ? $imap->getSummary($result[0]) : NULL;
        
        return $message;
    }
    
    /**
     * get imap backend
     *
     * @param Felamimail_Model_Folder $_folder
     * @return Felamimail_Backend_ImapProxy
     */
    protected function _getImapFromFolder($_folder) {
        if ($_folder->account_id == $this->_account->getId()) {
            $imap = $this->_imap;
        } else {
            $imap = Expressomail_Backend_ImapFactory::factory($_folder->account_id);
        }
        
        return $imap;
    }
    
    /**
     * search for messages on imap server
     *
     * @param string $_testHeaderValue
     * @param Felamimail_Model_Folder $_folder
     * @return array
     */
    protected function _searchOnImap($_testHeaderValue, $_folder, $_imap = NULL)
    {
        if ($_imap === NULL) {
            $imap = $this->_getImapFromFolder($_folder);
        } else {
            $imap = $_imap;
        }
        
        $imap->expunge($_folder->globalname);
        $result = $imap->search(array(
            'HEADER X-Tine20TestMessage ' . $_testHeaderValue
        ));
        
        return $result;
     }
    
    /**
     * append message (from given filename) to cache
     *
     * @param string $_filename
     * @param string $_folder
     * @param array $_replacements
     */
    protected function _appendMessage($_filename, $_folder, $_replacements = array())
    {
        $filename = dirname(dirname(__FILE__)) . '/files/' . $_filename;
        if (! empty($_replacements)) {
            $message = file_get_contents($filename);
            $message = preg_replace('/' . preg_quote($_replacements[0], '/') . '/m', $_replacements[1], $message);
        } else {
            $message = fopen($filename, 'r');
        }
        $this->_controller->appendMessage($_folder, $message);
    }
    
    /**
     * get message filter
     *
     * @param string $_folderId
     * @return Felamimail_Model_MessageFilter
     */
    protected function _getFilter($_folderId)
    {
        return new Expressomail_Model_MessageFilter(array(
            array('field' => 'folder_id', 'operator' => 'equals', 'value' => $_folderId)
        ));
    }

    /**
     * get folder
     *
     * @return Expressomail_Model_Folder
     */
    public function getFolder($_folderName = null, $_account = NULL)
    {
        $folderName = ($_folderName !== null) ? $_folderName : $this->_testFolderName;
        $account = ($_account !== NULL) ? $_account : $this->_account;

        if ($_folderName == 'INBOX') {
            $filter = new Expressomail_Model_FolderFilter(array(
                array('field' => 'globalname', 'operator' => 'equals', 'value' => '',),
                array('field' => 'account_id', 'operator' => 'equals', 'value' => $account->getId())
            ));
        } else {
            $filter = new Expressomail_Model_FolderFilter(array(
                array('field' => 'globalname', 'operator' => 'startswith', 'value' => 'INBOX'),
                array('field' => 'account_id', 'operator' => 'equals', 'value' => $account->getId()),
            ));
        }
        $result = Expressomail_Controller_Folder::getInstance()->search($filter);
        $folder = $result->filter(strpos($_folderName, '/') === FALSE ? 'localname' : 'globalname', $folderName)->getFirstRecord();
        if (empty($folder)) {
            $_folderName = strpos($_folderName, 'INBOX/') == 0 ? substr($_folderName, 5) : $_folderName;
            $folder = Expressomail_Controller_Folder::getInstance()->create($account, $_folderName, 'INBOX');
        }

        return $folder;
    }

    /**
     * search message by header (X-Tine20TestMessage) and add it to cache
     *
     * @param string $_testHeaderValue
     * @param Expressomail_Model_Folder $_folder
     * @param boolean $assert
     * @param string $testHeader
     * @return Expressomail_Model_Message|NULL
     */
    public function searchAndCacheMessage($_testHeaderValue, $_folder = NULL, $assert = TRUE, $testHeader = 'X-Tine20TestMessage')
    {
        $folder = ($_folder !== NULL) ? $_folder : $this->_folder;
        $message = $this->_searchMessage($_testHeaderValue, $folder, $assert, $testHeader);

        if ($message === NULL && ! $assert) {
            return NULL;
        }

//        $cachedMessage = $this->_cache->addMessage($message, $folder);
//        if ($cachedMessage === FALSE) {
//            // try to add message again (it had a duplicate)
//            $this->_cache->clear($folder);
//            $cachedMessage = $this->_cache->addMessage($message, $folder);
//        }
//
//        if ($assert) {
//            $this->assertTrue($cachedMessage instanceof Expressomail_Model_Message, 'could not add message to cache');
//        }
//
//        $this->_createdMessages->addRecord($cachedMessage);
//
//        return $cachedMessage;

        $expressoMessage = new Expressomail_Model_Message(array(
            'account_id'    => $_folder->account_id,
            'messageuid'    => $message['uid'],
            'folder_id'     => $_folder->getId(),
            'timestamp'     => Tinebase_DateTime::now(),
            'received'      => Expressomail_Message::convertDate($message['received']),
            'size'          => $message['size'],
            'flags'         => $message['flags'],
        ));

        $expressoMessage->parseStructure($message['structure']);
        $expressoMessage->parseHeaders($message['header']);
        $expressoMessage->parseBodyParts();

        $attachments = Expressomail_Controller_Message::getInstance()->getAttachments($expressoMessage);
        $expressoMessage->has_attachment = (count($attachments) > 0) ? true : false;

        return $expressoMessage;
    }

}
