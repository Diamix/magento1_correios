<?php
/**
 * Diamix_Correios Module
 */

/**
 * Data Helper
 * 
 * Default module helper.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get Config Value
     * 
     * @param string $config Config key
     * @return string
     */
    public function getConfigValue($config)
    {
        return Mage::getStoreConfig('carriers/Diamix_Correios/' . $config);
    }
    
    /**
     * Get Free Shipping Method
     * 
     * Return the code for the method used as free shipping.
     * @param array $finalQuotes The complete quote array
     * @return string|bool
     */
    public function getFreeShippingMethod($finalQuotes)
    {
        if ($this->getConfigValue('usecontract') == 1) {
            if ($this->getConfigValue('free_method_contract') != 'none') {
                // case 1, only PAC
                if ($this->getConfigValue('free_method_contract') == 'onlypac') {
                    return $this->whichPac();
                }
                // case 2, first PAC, then Sedex
                if ($this->getConfigValue('free_method_contract') == 'firstpacthensedex') {
                    $pac = $this->whichPac();
                    $sedex = $this->whichSedex();
                    $pacExists = false;
                    $sedexExists = false;
                    
                    // loop through quotes to determine the bevahiour to addopt
                    foreach ($finalQuotes as $key => $final) {
                        if ($key == $pac) {
                            $pacExists = true;
                        }
                        if ($key == $sedex) {
                            $sedexExists = true;
                        }
                    }
                    
                    // define the free method
                    if ($pacExists) {
                        return $pac;
                    }
                    if ($sedexExists) {
                        return $sedex;
                    }
                }                
            }
        } else {
            if ($this->getConfigValue('free_method_simple') != 'none') {
                // case 1, only PAC
                if ($this->getConfigValue('free_method_simple') == 'onlypac') {
                    return $this->whichPac();
                }
                // case 2, first PAC, then Sedex
                if ($this->getConfigValue('free_method_simple') == 'firstpacthensedex') {
                    $pac = $this->whichPac();
                    $sedex = $this->whichSedex();
                    $pacExists = false;
                    $sedexExists = false;
                    
                    // loop through quotes to determine the bevahiour to addopt
                    foreach ($finalQuotes as $key => $final) {
                        if ($key == $pac) {
                            $pacExists = true;
                        }
                        if ($key == $sedex) {
                            $sedexExists = true;
                        }
                    }
                    
                    // define the free method
                    if ($pacExists) {
                        return $pac;
                    }
                    if ($sedexExists) {
                        return $sedex;
                    }
                }                
            }
        }
        return false;
    }
    
    /**
     * whichPac
     * 
     * Returns the PAC code to be used on contracts. New code has precedence.
     * @returns string|bool
     */
    protected function whichPac()
    {
        $pac = false;
        if ($this->getConfigValue('usecontract') == 1) {
            $availableMethodsRaw = $this->getConfigValue('contractmethods');
            $availableMethods = explode(',', $availableMethodsRaw);
            
            // first, get the old code
            if (in_array('41068', $availableMethods)) {
                $pac = '41068';
            }
            
            // after, if new method is available, overwrite it
            if (in_array('04669', $availableMethods)) {
                $pac = '04669';
            }
        } else {
            $availableMethodsRaw = $this->getConfigValue('simplemethods');
            $availableMethods = explode(',', $availableMethodsRaw);
            
            // first, get the old code
            if (in_array('41106', $availableMethods)) {
                $pac = '41106';
            }
            
            // after, if new method is available, overwrite it
            if (in_array('04510', $availableMethods)) {
                $pac = '04510';
            }
        }
        return $pac;
    }
    
    /**
     * whichSedex
     * 
     * Returns the Sedex code to be used on contracts. New code has precedence.
     * @returns string|bool
     */
    protected function whichSedex()
    {
        $sedex = false;
        if ($this->getConfigValue('usecontract') == 1) {
            $availableMethodsRaw = $this->getConfigValue('contractmethods');
            $availableMethods = explode(',', $availableMethodsRaw);
            
            // first, get the old code
            if (in_array('40096', $availableMethods)) {
                $sedex = '40096';
            }
            
            // after, if new method is available, overwrite it
            if (in_array('04162', $availableMethods)) {
                $sedex = '04162';
            }
        } else {
            $availableMethodsRaw = $this->getConfigValue('simplemethods');
            $availableMethods = explode(',', $availableMethodsRaw);
            
            // first, get the old code
            if (in_array('40010', $availableMethods)) {
                $sedex = '40010';
            }
            
            // after, if new method is available, overwrite it
            if (in_array('04014', $availableMethods)) {
                $sedex = '04014';
            }
        }
        return $sedex;
    }
    
    /**
     * Add Handling Fee
     * 
     * Add Handling Fee when enabled.
     * @param float $cost The quote cost
     * @return float
     */
    public function addHandlingFee($cost)
    {
        if ($this->getConfigValue('handling_fee')) {
            $cost += $this->convertCommaToDot($this->getConfigValue('handling_fee_value'));
        }
        return $cost;
    }

    /**
     * Verify Charge Code
     *
     * Returns true if a given code belongs to acobrar_code config.
     * @param string $code
     * @return bool
     */
    public function verifyToChargeCode($code)
    {
        $toChargeCodes = explode(',', $this->getConfigValue('acobrar_code'));
        if (in_array($code, $toChargeCodes)) {
            return true;
        }
        return false;
    }

    /**
     * Verify Declared Value
     * 
     * Verify if package value is greater than minimum when declared value is enabled
     * @param float $declaredValue The declared value
     * @return boolean
     */
    public function verifyDeclaredValue($declaredValue)
    {
        if ($declaredValue < $this->getConfigValue('gateway_limits/min_declared_value')) {
            return false;
        }
        return true;
    }
    
    /**
     * Sanitize postcodes
     * 
     * @param string $postcode Postcode
     * @return string
     */
    public function sanitizePostcode($postcode)
    {
        $postcode = str_replace(' ', '', str_replace(',', '', str_replace('/', '', str_replace('.', '', str_replace('-', '', $postcode)))));
        if (strlen($postcode < 8)) {
            $postcode = str_pad($postcode, '8', '0', STR_PAD_LEFT);
        }
        return $postcode;
    }
    
    /**
     * Change weight to kilos
     * 
     * @param float $weight Package weight
     * @return float
     */
    public function changeWeightToKilos($weight)
    {
        if ($this->getConfigValue('weight_unit') == 'kg') {
            return $weight;
        } elseif ($this->getConfigValue('weight_unit') == 'g') {
            return ($weight / 1000);
        } else {
            return null;
        }
    }
    
    /**
     * Change dimension to centimeters
     * 
     * @param float $dimension Package dimension
     * @return float
     */
    public function changeDimensionToCentimeters($dimension)
    {
        if ($this->getConfigValue('dimension_unit') == 'cm') {
            return $dimension;
        } elseif ($this->getConfigValue('dimension_unit') == 'm') {
            return ($dimension * 100);
        } elseif ($this->getConfigValue('dimension_unit') == 'mm') {
            return $dimension / 10;
        } else {
            return null;
        }
    }
    
    /**
     * Convert float with comma to dot
     * 
     * @param string $value Initial value
     * @return float
     */
    public function convertCommaToDot($value)
    {
        return (float)str_replace(',', '.', $value);
    }
    
    /**
     * Convert float with dot to comma
     * 
     * @param string $value Initial value
     * @return float
     */
    public function convertDotToComma($value)
    {
        return str_replace('.', ',', $value);
    }
}