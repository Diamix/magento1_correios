<?php
/**
 * Diamix_Correios Module
 */

/**
 * Tracking Sources List
 * 
 * Lists tracking sources, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Trackingsources
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'correios', 'label' => Mage::helper('Diamix_Correios')->__('Correios Webservice')),
            /*array('value' => 'agenciaideias', 'label' => Mage::helper('Diamix_Correios')->__('API Agência Ideias')),*/
		);
    }
}
