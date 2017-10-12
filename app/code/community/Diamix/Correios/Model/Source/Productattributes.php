<?php
/**
 * Diamix_Correios Module
 */

/**
 * Product Attributes List
 * 
 * Get products attributes and return them as list, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Source_Productattributes
{
    /**
     * toOptionArray
     * 
     * Returns a list with user defined attributes, which means, custom attributes.
     * @return Array
     */
    public function toOptionArray()
    {
        // Pedro Teixeira attributes
        $ptAttributes = array('volume_altura', 'volume_comprimento', 'volume_largura');

        $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection');
        $array = array(
            array(
                'value' => 'none',
                'label' => Mage::helper('Diamix_Correios')->__('No attribute selected'),
            )
        );
        foreach ($productAttributes as $pa) {
            // list attributes used by Pedro Teixeira module and all that are not system ones
            if ($pa->getIsUserDefined() == 1  || in_array($pa->getAttributeCode(), $ptAttributes)) {
                $option = array(
                    'value' => $pa->getAttributeCode(),
                    'label' => $pa->getFrontendLabel(),
                );
                array_push($array, $option);
                unset($option);
            }
        }
        return $array;
    }
}
