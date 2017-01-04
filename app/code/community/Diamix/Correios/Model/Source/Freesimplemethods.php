<?php
/**
 * Diamix_Correios Module
 */

/**
 * Simple Methods List.
 * 
 * Lists simple methods (without contract), including 'none' option, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Freesimplemethods
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'none', 'label' => Mage::helper('Diamix_Correios')->__('None')),
    		array('value' => '41106', 'label' => 'PAC (41106)'),
            array('value' => '40010', 'label' => 'Sedex (40010)'),
		);
    }
}
