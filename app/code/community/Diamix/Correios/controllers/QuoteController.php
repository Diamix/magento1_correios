<?php
/**
 * Diamix_Correios Module
 */

/**
 * Quote Controller
 * 
 * Controller in charge of handling quotes requested from product page.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_QuoteController extends Mage_Core_Controller_Front_Action
{
    /**
     * Index action
     */
    public function indexAction()
    {
        // verify if a product has been sent
        if (!$this->getRequest()->getParam('currentProduct')) {
            die;
        }
        
        // prepare basic data
        $postcode = (int) str_replace('-', '', str_replace('.', '', $this->getRequest()->getParam('postcode')));
        $productId = (int) $this->getRequest()->getParam('currentProduct');
        $productQty = (int) $this->getRequest()->getParam('qty');
        if ($productQty == 0 || $productQty == null) {
            $productQty = 1;
        }
        $params = $this->getRequest()->getParams();
        
        // get estimate quote        
        $shippingHtml = Mage::getModel('Diamix_Correios/estimate')->getEstimate($postcode, $productId, $productQty, $params);
        if ($shippingHtml) {
            echo json_encode($shippingHtml);
        }
    }
}
