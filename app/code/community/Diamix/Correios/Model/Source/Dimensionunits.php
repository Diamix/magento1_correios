<?php
/**
 * Diamix_Correios Module
 */

/**
 * Dimension Units List
 * 
 * Lists dimension units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Dimensionunits
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'cm', 'label' => Mage::helper('Diamix_Correios')->__('centimeters')),
            array('value' => 'mm', 'label' => Mage::helper('Diamix_Correios')->__('milimeters')),
            array('value' => 'm', 'label' => Mage::helper('Diamix_Correios')->__('meters')),
		);
    }
}
