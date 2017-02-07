<?php
/**
 * Diamix_Correios Module
 */

/**
 * Carrier Model
 * 
 * Model containing main methods to integrate to Correios webservice.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 1.0
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Diamix_Correios_Model_Carrier_Correios extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * Method unique code
     * @access protected
     */
    protected $_code = 'Diamix_Correios';
    
    /**
     * Quote result
     * @access protected
     */
    protected $_result = null;
    
    /**
     * From Zip
     * @access protected
     */
    protected $fromZip;
    
    /**
     * To Zip
     * @access protected
     */
    protected $toZip;
    
    /**
     * Package Weight
     * @access protected
     */
    protected $packageWeight;
    
    /**
     * Package Value
     * @access protected
     */
    protected $packageValue;
    
    /**
     * Get Allowed Methods
     */
    public function getAllowedMethods()
    {
        return array($this->_code => Mage::helper('Diamix_Correios')->getConfigValue('title'));
    }
    
    /**
     * Collect Rates
     * 
     * Receives shipping request and process it. If there are quotes, return them. Else, return false.
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return array|bool
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // double checking if this method is active
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        // prepare items object according to environment
        if (!Mage::app()->getStore()->isAdmin()) {
            // quote on frontend
            if (Mage::helper('Diamix_Correios')->getConfigValue('active_frontend') == 1) {
                $items = Mage::getModel('checkout/cart')->getQuote()->getAllVisibleItems();
            } else {
                return false;
            }
        } else {
            // quote on backend
            $items = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getAllVisibleItems();
        }
        
        // perform initial validation
        $initialValidation = $this->performInitialValidation($request);
        if (!$initialValidation) {
            return false;
        }
        // perform validate dimensions
        $packages = $this->preparePackages($items, Mage::helper('Diamix_Correios')->getConfigValue('validate_dimensions'));
        if (!$packages) {
            Mage::log('Diamix_Correios: There was an unexpected error when preparing the packages.');
            return false;
        }
        
        // initialize quote result object
        $this->_result = Mage::getModel('shipping/rate_result');
                
        // get allowed methods, passing free shipping if allowed
        $this->getQuotes($packages, $request->getFreeShipping());

        return $this->_result;
    }
    
    /**
     * Initial validation
     * 
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return bool
     */
    protected function performInitialValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        // verify sender and receiver countries as 'BR'
        $senderCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
        if (!Mage::app()->getStore()->isAdmin()) {
            // quote on frontend
            $receiverCountry = $request->getDestCountryId();
        } else {
            // quote on backend
            $receiverCountry = $request->getCountryId();
        }
        
        if ($senderCountry != 'BR') {
            Mage::log('Diamix_Correios: This method is active but default store country is not set to Brazil');
            return false;
        }
        if ($receiverCountry != 'BR') {
            return false;
        }
        
        // prepare postcodes and verify them
        $this->fromZip = Mage::helper('Diamix_Correios')->sanitizePostcode(Mage::getStoreConfig('shipping/origin/postcode', $this->getStore()));
        $this->toZip = Mage::helper('Diamix_Correios')->sanitizePostcode($request->getDestPostcode());
        if (!preg_match("/^([0-9]{8})$/", $this->fromZip) || !preg_match("/^([0-9]{8})$/", $this->toZip)) {
            return false;
        }
        
        // prepare package weight and verify it; this module works with kilos as standard weight unit
        $this->packageWeight = $request->getPackageWeight();
        if (Mage::helper('Diamix_Correios')->getConfigValue('weight_unit') != 'kg') {
            $this->packageWeight = Mage::helper('Diamix_Correios')->changeWeightToKilos($this->packageWeight);
        }
        $minWeight = Mage::helper('Diamix_Correios')->getConfigValue('min_order_weight') ? Mage::helper('Diamix_Correios')->getConfigValue('min_order_weight') : Mage::helper('Diamix_Correios')->getConfigValue('standard_min_order_weight');
        $maxWeight = Mage::helper('Diamix_Correios')->getConfigValue('max_order_weight') ? Mage::helper('Diamix_Correios')->getConfigValue('max_order_weight') : Mage::helper('Diamix_Correios')->getConfigValue('standard_max_order_weight');
        if ($this->packageWeight < $minWeight || $this->packageWeight > $maxWeight) {
            return false;
        }
        
        // prepare package value and verify it
        $this->packageValue = $request->getBaseCurrency()->convert($request->getPackageValue(), $request->getPackageCurrency());
        $minValue = Mage::helper('Diamix_Correios')->getConfigValue('min_order_value') ? Mage::helper('Diamix_Correios')->getConfigValue('min_order_value') : Mage::helper('Diamix_Correios')->getConfigValue('standard_min_order_value');
        $maxValue = Mage::helper('Diamix_Correios')->getConfigValue('max_order_value') ? Mage::helper('Diamix_Correios')->getConfigValue('max_order_value') : Mage::helper('Diamix_Correios')->getConfigValue('standard_max_order_value');
        
        if ($this->packageValue < $minValue || $this->packageValue > $maxValue) {
            return false;
        }
        return true;
    }
    
    /**
     * Prepare Packages
     * 
     * Used to create packages, according to dimensions rules or not.
     * @param Mage_Checkout_Model_Cart $items
     * @param bool $validate Validate Dimensions. This allows to override store config, if needed
     * @return bool
     * 
     * @todo rebuild dimensions from DB / config.xml, wrong names structure
     */
    protected function preparePackages($items, $validate = 0)
    {
        $helper = Mage::helper('Diamix_Correios');
        
        // get attribute codes
        $lengthCode = $helper->getConfigValue('attribute_length') != 'none' ? $helper->getConfigValue('attribute_length') : null;
        $widthCode = $helper->getConfigValue('attribute_width') != 'none' ? $helper->getConfigValue('attribute_width') : null;
        $heightCode = $helper->getConfigValue('attribute_height') != 'none' ? $helper->getConfigValue('attribute_height') : null;
        
        // if validate dimensions, use params; else set fictional params
        if ($validate == 1) {
            // define package min and max dimensions
            $minLength = $helper->getConfigValue('standard_min_length');
            $minWidth = $helper->getConfigValue('standard_min_width');
            $minHeight = $helper->getConfigValue('standard_min_height');
            $maxLength = $helper->getConfigValue('standard_max_length');
            $maxWidth = $helper->getConfigValue('standard_max_width');
            $maxHeight = $helper->getConfigValue('standard_max_height');
            $maxSum = 200; // 200cm
            
            // define package min and max weight and value, comparing custom and standard values, always in centimeters
            $minWeight = ($helper->getConfigValue('min_order_weight') >= $helper->getConfigValue('standard_min_order_weight')) ? $helper->getConfigValue('min_order_weight') : $helper->getConfigValue('standard_min_order_weight');
            $maxWeight = ($helper->getConfigValue('max_order_weight') <= $helper->getConfigValue('standard_max_order_weight')) ? $helper->getConfigValue('max_order_weight') : $helper->getConfigValue('standard_max_order_weight');
            $minValue = ($helper->getConfigValue('min_order_value') >= $helper->getConfigValue('standard_min_order_value')) ? $helper->getConfigValue('min_order_value') : $helper->getConfigValue('standard_min_order_value');
            $maxValue = ($helper->getConfigValue('max_order_value') <= $helper->getConfigValue('standard_max_order_value')) ? $helper->getConfigValue('max_order_value') : $helper->getConfigValue('standard_max_order_value');
        } else {
            // hardcoded values to avoid undefined variable errors
            $minHeight = 0;
            $minWidth = 0;
            $minLenght = 0;
            $maxHeight = 10000; // 10.000 cm
            $maxWidth = 10000;
            $maxLength = 10000;
            $maxSum = 30000; // 30.000 cm
            $minWeight = 0;
            $maxWeight = 1000; // 1000 kg
            $minValue = 0;
            $maxValue = 100000; // $ 100.000
        }
        
        // define packages array and first package
        $packages = array();
        $firstPackage = new Diamix_Correios_Model_Package();
        array_push($packages, $firstPackage);
        
        // loop through items to validate dimensions and define packages
        foreach ($items as $item) {
            // get product data
            $_product = $item->getProduct();
            $qty = $item->getQty();
            if ($qty == 0) {
                continue;
            }
            
            for ($i = 0; $i < $qty; $i++) {
                // set item dimensions; if the custom dimension is less than minimum, use standard; if greater than maximum, log and return false for the whole quote
                // set item height
                if ($heightCode) {
                    $itemHeight = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $heightCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $heightCode, $this->getStore()) : $helper->getConfigValue('standard_height');
                    
                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemHeight = $helper->changeDimensionToCentimeter($itemHeight);
                    }
                    
                    if ($itemHeight < $minHeight) {
                        $itemHeight = $minHeight;
                    }
                    if ($itemHeight > $maxHeight) {
                        Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect height set: ' . $itemHeight . '. Max height: ' . $maxHeight);
                        return false;
                    }
                } else {
                    $itemHeight = $helper->getConfigValue('standard_height');
                }
                
                // set item width
                if ($widthCode) {
                    $itemWidth = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $widthCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $widthCode, $this->getStore()) : $helper->getConfigValue('standard_width');
                    
                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemWidth = $helper->changeDimensionToCentimeter($itemWidth);
                    }
                    
                    if ($itemWidth < $minWidth) {
                        $itemWidth = $minWidth;
                    }
                    if ($itemWidth > $maxWidth) {
                        Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect width set: ' . $itemWidth . '. Max width: ' . $maxWidth);
                        return false;
                    }
                } else {
                    $itemWidth = $helper->getConfigValue('standard_width');
                }
                
                // set item length
                if ($lengthCode) {
                    $itemLength = Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $lengthCode, $this->getStore()) ? Mage::getResourceModel('catalog/product')->getAttributeRawValue($_product->getId(), $lengthCode, $this->getStore()) : $helper->getConfigValue('standard_length');
                    
                    // convert to centimeter, if needed
                    if ($helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemLength = $helper->changeDimensionToCentimeter($itemLength);
                    }
                    
                    if ($itemLength < $minLength) {
                        $itemLength = $minLength;
                    }
                    if ($itemLength > $maxLength) {
                        Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect length set: ' . $itemLength . '. Max length: ' . $maxLength);
                        return false;
                    }
                } else {
                    $itemLength = $helper->getConfigValue('standard_length');
                }
                
                // verify dimensions sum
                $dimensionsSum = $itemHeight + $itemWidth + $itemLength;
                if ($dimensionsSum > $maxSum) {
                    Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect sum: ' . $dimensionsSum);
                    return false;
                }
                
                // get product weight
                $itemWeight = $_product->getWeight();
                if ($itemWeight < $minWeight) {
                    $itemWeight = $minWeight;
                }
                if ($itemWeight > $maxWeight) {
                    Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect weight set: ' . $itemWeight . '. Max weight: ' . $maxWeight);
                    return false;
                }
                
                // get product value
                $itemValue = $_product->getFinalPrice();
                if ($itemValue < $minValue || $itemValue > $maxValue) {
                    Mage::log('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect value set: ' . $itemValue);
                    return false;
                }
                
                // loop through created packages
                $packagesCount = count($packages);
                $loop = 1;
                
                foreach ($packages as $pa) {
                    // verify if there is enough space to this item within a given package
                    if (($pa->getLength() + $itemLength) <= $maxLength && ($pa->getWidth() + $itemWidth) <= $maxWidth && ($pa->getHeight() + $itemHeight) <= $maxHeight && (($pa->getValue() + $itemValue) <= $maxValue) && ($pa->getWeight() + $itemWeight) <= $maxWeight && ($pa->getSum() + $dimensionsSum) <= $maxSum) {
                        $pa->addLength($itemLength);
                        $pa->addWidth($itemWidth);
                        $pa->addHeight($itemHeight);
                        $pa->addWeight($itemWeight);
                        $pa->addValue($itemValue);
                        $pa->addItem($_product);
                        break;
                    } else {
                        // verify if there are more packages to test before creating a new one
                        if ($loop < $packagesCount) {
                            // if there are more packages, continue to loop
                            $loop++;
                            continue;
                        } else {
                            // create a new package and insert item
                            $pb = new Diamix_Correios_Model_Package();
                            $pb->addLength($itemLength);
                            $pb->addWidth($itemWidth);
                            $pb->addHeight($itemHeight);
                            $pb->addWeight($itemWeight);
                            $pb->addValue($itemValue);
                            $pb->addItem($_product);
                            
                            array_push($packages, $pb);
                            $packagesCount++;
                            break;
                        }
                    }
                }
            }
        }
        return $packages;
    }
    
    /**
     * Get Quotes
     * 
     * @param array $packages Packages list
     * @param bool $freeShipping Determines if free shipping is available
     * @return array
     */
    protected function getQuotes($packages, $freeShipping = false)
    {
        $helper = Mage::helper('Diamix_Correios');
        
        // get services
        if ($helper->getConfigValue('usecontract') == 1) {
            $services = $helper->getConfigValue('contractmethods');
        } else {
            $services = $helper->getConfigValue('simplemethods');
        }
        
        // loop through packages
        foreach ($packages as $package) {
            $params = array(
                'services' => $services,
                'zipFrom' => $this->fromZip,
                'zipTo' => $this->toZip,
                'weight' => $package->getWeight(),
                'length' => $package->getLength(),
                'width' => $package->getWidth(),
                'height' => $package->getHeight(),
                'value' => $package->getValue(),
            );
            
            // send request to webservice
            $quoteRequest = $this->processGatewayRequest($params);
            
            if (!$quoteRequest) {
                Mage::log('Diamix_Correios: There was an error when getting a quote for a package with following data. Weight: ' . $package->getWeight() . ', length: ' . $package->getLength() . ', width: ' . $package->getWidth() . ', height: ' . $package->getHeight(), ', value: ' . $package->getValue());
                return false;
            }
            
            // split package quote response, allowing different services to be put together
            $finalQuotes = array();
            $i = 0;
            foreach ($quoteRequest as $partialQuote) {
                // create array entry
                if ($i == 0) {
                    $finalQuotes[$partialQuote['id']]['cost'] = 0;
                    $finalQuotes[$partialQuote['id']]['delivery'] = 0;
                }
                
                // verify data to prevent wrong values; if incorrect value is provided, all service will be shut down
                if ($partialQuote['cost'] <= 0) {
                    $finalQuotes[$partialQuote['id']]['cost'] = -100;
                    continue;
                }
                
                // sum to total
                $finalQuotes[$partialQuote['id']]['cost'] += $partialQuote['cost'];
                $finalQuotes[$partialQuote['id']]['delivery'] += $partialQuote['delivery'];
                $i++;
            }
        }
        
        // foreach service, append quote result
        foreach ($finalQuotes as $key => $final) {
            if ($freeShipping == 1) {
                $quoteCost = 0;
            } else {
                $quoteCost = $final['cost'];
            }
            $shippingMethod = $key;
            $shippingTitle = $helper->getConfigValue('serv_' . $key);
            $shippingCost = $quoteCost;
            $shippingDelivery = $final['deliver'];
            $this->appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost, $shippingDelivery, $freeShipping);
        }
        return $this->_result;
    }
    
    /**
     * Append shipping return
     * 
     * Used to process shipping return and append it to main object
     * @param string $shippingMethod Shipping method code
     * @param string $shippingTitle Shipping method title
     * @param float $shippingCost Cost of this method
     * @param int $shippingDelivery Estimate time to delivery
     * @return bool
     * 
     * @todo implement Sedex a Cobrar, estimate deliver
     * @todo review method names, free shipping
     */
    protected function appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost = 0, $shippingDelivery = 0, $freeShipping = false)
    {
        $helper = Mage::helper('Diamix_Correios');
        
        // preparing and populating the shipping method
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($helper->getConfigValue('title'));
        $method->setMethod($shippingMethod);
        $method->setCost($shippingCost);
        
        // including estimate time of delivery
        if ($helper->getConfigValue('show_delivery_days')) {
            $shippingDelivery += $helper->getConfigValue('add_delivery_days');
            if ($shippingDelivery > 0) {
                $deliveryText = sprintf($helper->getConfigValue('delivery_message'), $shippingDelivery);
                $shippingTitle .= ' - ' . $deliveryText;
            }
        }
        $method->setMethodTitle($shippingTitle);
        
        // applying extra fee if required
        if ($freeShipping) {
            $shippingPrice = $shippingCost;
        } else {
            $shippingPrice = $shippingCost + $helper->getConfigValue('add_extra_fee');
        }
        
        $method->setPrice($shippingPrice);
        $this->_result->append($method);
    }
    
    /**
     * Is Tracking Available
     */
    public function isTrackingAvailable()
    {
        return true;
    }
    
    /**
     * Get Tracking Info
     * 
     * Method to be triggered when a tracking info is requested.
     * @param array $trackings Trackings
     * @return Mage_Shipping_Model_Tracking_Result
     */
    public function getTrackingInfo($trackings)
    {
        // instatiate the object and get tracking results
        $this->_result = Mage::getModel('shipping/tracking_result');
        foreach ((array) $trackings as $trackingCode) {
            $this->requestTrackingInfo($trackingCode);
        }
        
        // check results
        if ($this->_result instanceof Mage_Shipping_Model_Tracking_Result){
            if ($trackings = $this->_result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($this->_result) && !empty($this->_result)) {
            return $this->_result;
        } else {
            return false;
        }
    }
    
    /**
     * Request Tracking Info
     * 
     * Get data from the API regarding tracking code
     * @param string $trackingCode Tracking code
     * @return bool
     * 
     * @todo this is a fake method, must be implemented
     */
    protected function requestTrackingInfo($trackingCode)
    {
        // prepare data to connect to API
        $data = array(
            'trackingCode' => $trackingCode,
        );
        $trackingRequest = $this->processGatewayRequest($data, 'tracking');
        
        if (!$trackingRequest) {
            return false;
        }

        $progress = array();
        foreach ($trackingRequest as $code) {
            foreach($code->steps as $step) {
                $description = '';
                $datetime = explode(' ', $step->date); 
                $locale = new Zend_Locale('pt_BR');
                $date = '';
                $date = new Zend_Date($datetime[0], 'dd/MM/YYYY', $locale);
    
                $track = array(
                    'deliverydate' => $date->toString('YYYY-MM-dd'),
                    'deliverytime' => $datetime[1],
                    'deliverylocation' => htmlentities($step->location),
                    'status' => htmlentities($step->status),
                    'activity' => htmlentities($step->activity),
                );
                $progress[] = $track;
            }
        }

        if (!empty($progress)) {
            $track = $progress[0];
            $track['progressdetail'] = $progress;

            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setTracking($trackingCode);
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->addData($track);

            $this->_result->append($tracking);
            return true;
        }
        return false;
    }
    
    /**
     * Process Gateway Request
     * 
     * Connects to Correios' webserver and process return.
     * @param array $params Params to perform the quote {services, zipFrom, zipTo, weight, height, width, length, value}
     * @param boolean $logger Log errors
     * @return array
     * @see https://www.correios.com.br/para-voce/correios-de-a-a-z/pdf/calculador-remoto-de-precos-e-prazos/manual-de-implementacao-do-calculo-remoto-de-precos-e-prazos   Manual de Implementação do Cálculo Remoto de Preços e Prazos
     * 
     * @todo error append
     * @todo rewrite username and password
     */
    protected function processGatewayRequest($params, $logger = true)
    {
        $helper = Mage::helper('Diamix_Correios');
        $url = $helper->getConfigValue('url_ws_correios');
        $username = $helper->getConfigValue('carrier_username') ? $helper->getConfigValue('carrier_username') : '';
        $password = $helper->getConfigValue('carrier_password') ? $helper->getConfigValue('carrier_password') : '';
        
        // verify mandatory data
        if (!array_key_exists('services', $params) || !array_key_exists('zipFrom', $params) || !array_key_exists('zipTo', $params) || !array_key_exists('weight', $params) || !array_key_exists('height', $params) || !array_key_exists('width', $params) || !array_key_exists('length', $params) || !array_key_exists('value', $params)) {
            if ($logger) {
                Mage::log('Diamix_Correios: Missing mandatory data when triggering connection to Correios.');
            }
            return false;
        }
        
        // verify valid value and weight
        if ($params['value'] < $helper->getConfigValue('gateway_limits/min_value') || $params['value'] > $helper->getConfigValue('gateway_limits/max_value') || $params['weight'] < $helper->getConfigValue('gateway_limits/min_weight') || $params['weight'] > $helper->getConfigValue('gateway_limits/max_weight')) {
            if ($logger) {
                Mage::log('Diamix_Correios: A package with incorrect value or weight was submitted to quote.');
            }
            return false;
        }
        // verify valid measurements
        if ($params['height'] < $helper->getConfigValue('gateway_limits/min_height') || $params['height'] > $helper->getConfigValue('gateway_limits/max_height') || $params['width'] < $helper->getConfigValue('gateway_limits/min_width') || $params['width'] > $helper->getConfigValue('gateway_limits/max_width') || $params['length'] < $helper->getConfigValue('gateway_limits/min_length') || $params['length'] > $helper->getConfigValue('gateway_limits/max_length')) {
            if ($logger) {
                Mage::log('Diamix_Correios: A package with incorrect dimensions was submitted to quote.');
            }
            return false;
        }
        
        // fill missing data with standard params
        if (!array_key_exists('nCdFormato', $params) || $params['nCdFormato'] == '') {
            $params['nCdFormato'] = '1';
        }
        if (!array_key_exists('nVlDiametro', $params) || $params['nVlDiametro'] == '') {
            $params['nVlDiametro'] = '0';
        }
        if (!array_key_exists('sCdMaoPropria', $params) || $params['sCdMaoPropria'] == '') {
            $params['sCdMaoPropria'] = 'N';
        }
        if (!array_key_exists('nVlValorDeclarado', $params) || $params['nVlValorDeclarado'] == '') {
            $params['nVlValorDeclarado'] = '0';
        }
        if (!array_key_exists('sCdAvisoRecebimento', $params) || $params['sCdAvisoRecebimento'] == '') {
            $params['sCdAvisoRecebimento'] = 'N';
        }
        
        // prepare data according to Correios definitions
        $data = array(
            'nCdEmpresa' => $username,
            'sDsSenha' => $password,
            'nCdServico' => $params['services'],
            'sCepOrigem' => $params['zipFrom'],
            'sCepDestino' => $params['zipTo'],
            'nVlPeso' => $params['weight'],
            'nCdFormato' => $params['nCdFormato'],
            'nVlAltura' => $params['height'],
            'nVlLargura' => $params['width'],
            'nVlComprimento' => $params['length'],
            'nVlDiametro' => $params['nVlDiametro'],
            'sCdMaoPropria' => $params['sCdMaoPropria'],
            'nVlValorDeclarado' => $params['nVlValorDeclarado'],
            'sCdAvisoRecebimento' => $params['sCdAvisoRecebimento'],
        );
        
        // connect to Correios and verify if there are errors
        $ws = new SoapClient($url);
        $correios = $ws->CalcPrecoPrazo($data);
        
        // return on connection error
        if (!$correios) {
            if ($logger) {
                Mage::log('Error when connecting to Correios webserver');
            }
            return false;
        }
        
        // verify return and process it
        $count = count($correios->CalcPrecoPrazoResult->Servicos->cServico);
        if ($count < 1) {
            return false;
        } elseif ($count == 1) {
            $quote = $correios->CalcPrecoPrazoResult->Servicos->cServico;
            if ($quote->Erro != 0) {
                // validate according to error categories and process it
                $error = $this->processRequestError($quote->Erro, $quote->MsgErro);
                if (!$error) {
                    return false;
                }
                
                // return value if error allow quotes
                if ($error['status'] == 'die') {
                    // error append
                    
                } elseif ($error['status'] == 'verify') {
                    if ($quote->Valor != 0) {
                        $response = array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }                
            } else {
                $response = array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega);
            }
        } else {
            $errors = false;
            $response = array();
            foreach ($correios->CalcPrecoPrazoResult->Servicos->cServico as $quote) {
                if ($quote->Erro != 0) {
                    // validate according to error categories and process it
                    $error = $this->processRequestError($quote->Erro, $quote->MsgErro);
                    if (!$error) {
                        continue;
                    }
                    
                    // return value if error allow quotes
                    if ($error['status'] == 'die') {
                        // error append
                        
                    } elseif ($error['status'] == 'verify') {
                        if ($quote->Valor != 0) {
                            array_push($response, array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega));
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } else {
                    array_push($response, array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega));
                }
            }
        }
        return $response;
	}
    
    /**
     * Process Request Error
     * 
     * Used to process errors when requesting data from Correios webservice
     * @param string $error The error code
     * @param string $errorMsg The error message
     * @return array {status, message}
     */
    protected function processRequestError($error, $errorMsg = 'No error message')
    {
        $helper = Mage::helper('Diamix_Correios');
        
        // check for fatal errors
        $dieErrors = explode(',', $helper->getConfigValue('die_errors'));
        if (in_array($error, $dieErrors)) {
            Mage::log('Diamix_Correios: There was a fatal error when getting a quote from Correios webservice. Error ID: ' . $error . ', Correios message: ' . $errorMsg);
            return array(
                'status' => 'die',
                'message' => $helper->getConfigValue('die_errors_message'),
            );
        }
        
        // check for fake errors, when it is not a real error
        $fakeErrors = explode(',', $helper->getConfigValue('fake_errors'));
        if (in_array($error, $fakeErrors)) {
            return array(
                'status' => 'verify',
            );
        }
        
        // check for client errors
        $clientErrors = explode(',', $helper->getConfigValue('client_errors'));
        if (in_array($error, $clientErrors)) {
            return array(
                'status' => 'die',
                'message' => $helper->getConfigValue('client_errors_message'),
            );
        }
        
        $storeErrors = explode(',', $helper->getConfigValue('store_errors'));
        if (in_array($error, $storeErrors)) {
            Mage::log('Diamix_Correios: There was an error when getting a quote from Correios webservice. This seems to be a misconfig on the store. Error ID: ' . $error . ', Correios message: ' . $errorMsg);
            return array(
                'status' => 'die',
                'message' => $helper->getConfigValue('store_errors_message'),
            );
        }
        Mage::log('Diamix_Correios: An error has triggered the Process Request Error method but it was not possible to verify this error. Error: ' . $error);
        return false;
    }
}
