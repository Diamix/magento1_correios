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
     * Return the code for the method used as free shipping
     * @return int
     */
    public function getFreeShippingMethod()
    {
        if ($this->getConfigValue('usecontract') == 1) {
            if ($this->getConfigValue('free_method_contract') != 'none') {
                return $this->getConfigValue('free_method_contract');
            }
        } else {
            if ($this->getConfigValue('free_method_simple') != 'none') {
                return $this->getConfigValue('free_method_simple');
            }
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
        return (float)str_replace(',', '.', str_replace('.', '', $value));
    }
    
    /**
     * Convert float with dot to comma
     * 
     * @param string $value Initial value
     * @return float
     */
    public function convertDotToComma($value)
    {
        return str_replace('.', ',', str_replace(',', '', $value));
    }
}