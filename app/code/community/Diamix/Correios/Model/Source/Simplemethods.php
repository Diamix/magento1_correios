<?php
/**
 * Diamix_Correios Module
 */

/**
 * Simple Methods List
 * 
 * Lists simple methods (without contract), to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Simplemethods
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => '04510', 'label' => 'PAC (04510)'),
            array('value' => '04014', 'label' => 'Sedex (04014)'),
            array('value' => '41106', 'label' => 'PAC - Método Antigo (41106)'),
            array('value' => '40010', 'label' => 'Sedex - Método Antigo (40010)'),
		);
    }
}
