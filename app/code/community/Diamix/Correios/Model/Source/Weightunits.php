<?php
/**
 * Diamix_Correios Module
 */

/**
 * Weight Units List
 * 
 * Lists weight units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Weightunits
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'kg', 'label' => Mage::helper('Diamix_Correios')->__('Kilos')),
            array('value' => 'g', 'label' => Mage::helper('Diamix_Correios')->__('Grams')),
		);
    }
}
