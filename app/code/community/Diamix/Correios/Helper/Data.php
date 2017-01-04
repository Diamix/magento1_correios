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
}