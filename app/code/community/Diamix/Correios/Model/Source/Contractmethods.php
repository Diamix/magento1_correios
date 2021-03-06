<?php
/**
 * Diamix_Correios Module
 */

/**
 * Contract Methods List
 * 
 * Lists contract methods, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Contractmethods
{
    /**
     * toOptionArray
     * 
     * @return array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => '04669', 'label' => 'PAC (04669)'),
            /*array('value' => '41300', 'label' => 'PAC Grandes Formatos (41300)'),*/
    		array('value' => '04162', 'label' => 'Sedex (04162)'),
    		array('value' => '40215', 'label' => 'Sedex 10 (40215)'),
            array('value' => '40169', 'label' => 'Sedex 12 (40169)'),
    		array('value' => '40290', 'label' => 'Sedex HOJE (40290)'),
            array('value' => '40126', 'label' => 'Sedex Pagamento na Entrega (40126)'),
            array('value' => '81019', 'label' => 'E-Sedex (81019)'),
            array('value' => '41068', 'label' => 'Antigo: PAC (41068)'),
            array('value' => '40096', 'label' => 'Antigo: Sedex (40096)'),
            array('value' => '40045', 'label' => 'Antigo: Sedex a Cobrar (40045)'),
		);
    }
}
