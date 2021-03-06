<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Sieve
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * abstract class to read and create Sieve scripts
 * 
 * @package     Felamimail
 * @subpackage  Sieve
 */
abstract class Felamimail_Sieve_Backend_Abstract
{
    /**
     * array of Sieve rules(Felamimail_Sieve_Rule)
     * 
     * @var array
     */
    protected $_rules = array();
    
    /**
     * the vacation object
     * 
     * @var Felamimail_Sieve_Vacation
     */
    protected $_vacation = NULL;
    
    /**
     * generator string in header
     * 
     * @var string
     */
    protected $_generatorString = "#Generated by Felamimail_Sieve\r\n";
    
    /**
     * return array of Felamimail_Sieve_Rule
     * 
     * @return array
     */
    public function getRules()
    {
        return $this->_rules;
    }
    
    /**
     * return vacation object
     * 
     * @return Felamimail_Sieve_Vacation
     */
    public function getVacation()
    {
        return $this->_vacation;
    }
    
    /**
     * parse Sieve script (only pseudo scripts get loaded)
     */
    abstract public function readScriptData();
    
    /**
     * add rule to script
     * 
     * @param Felamimail_Sieve_Rule $rule
     * @return Felamimail_Sieve_Vacation
     */
    public function addRule(Felamimail_Sieve_Rule $rule)
    {
        $this->_rules[$rule->getId()] = $rule;
    }
    
    /**
     * reset rules
     */
    public function clearRules()
    {
        $this->_rules = array();
    }
    
    /**
     * get sieve script as string
     * 
     * @return string
     */
    public function getSieve()
    {
        $rules = $this->_getRulesString();
        $vacation = $this->_getVacationString();
        $header = (! empty($rules) || ! empty($vacation)) ? $this->_getHeaderString() : '';
        
        $sieve = $header . "\r\n\r\n" . $rules . $vacation . "\r\n\r\n";
        
        return $sieve;
    }
    
    /**
     * get sieve header string
     * 
     * @return string
     */
    protected function _getHeaderString()
    {
        $header = $this->_generatorString;
        
        $require = $this->_getRequirements();
        if (!empty($require)) {
            $header .= 'require [' . implode(',', $require) .'];';
        }
        
        return $header;
    }

    /**
     * get sieve requirements
     * 
     * @return array
     */
    protected function _getRequirements()
    {
        $require = array();
        
        if (! empty($this->_rules)) {
            $require[] = '"fileinto"';
            $require[] = '"reject"';
            
            foreach ($this->_rules as $rule) {
                if ($rule->hasRegexCondition()) {
                    $require[] = '"regex"';
                    break;
                }
            }
        }
        
        if (! empty($this->_vacation) && $this->_vacation->isEnabled() === true) {
            $require[] = '"vacation"';
            
            if ($this->_vacation->useDates()) {
                $require[] = '"date"';
                $require[] = '"relational"';
            }
        }
        
        return $require;
    }

    /**
     * get sieve rules string
     * 
     * @return string
     */
    protected function _getRulesString()
    {
        $rules = '';
        
        ksort($this->_rules);
        foreach ($this->_rules as $rule) {
            if ($rule->isEnabled() === true) {
                $rules .= sprintf("%s %s", (empty($rules)) ? 'if' : 'elsif', $rule);
            }
        }
        
        return $rules;
    }
    
    /**
     * get sieve vacation string
     * 
     * @return string
     */
    protected function _getVacationString()
    {
        if ($this->_vacation && $this->_vacation->isEnabled()) {
            $vacation = $this->_vacation->__toString();
        } else {
            $vacation = '';
        }
        
        return $vacation;
    }

    /**
     * set vacation
     * 
     * @param Felamimail_Sieve_Vacation $vacation
     */
    public function setVacation(Felamimail_Sieve_Vacation $vacation)
    {
        $this->_vacation = $vacation;
    }
    
    /**
     * copy data from another script
     * 
     * @param Felamimail_Sieve_Backend_Abstract $_scriptToCopyFrom
     */
    public function getDataFromScript(Felamimail_Sieve_Backend_Abstract $_scriptToCopyFrom)
    {
        $this->_vacation = $_scriptToCopyFrom->getVacation();
        $this->_rules = $_scriptToCopyFrom->getRules();
    }
}
