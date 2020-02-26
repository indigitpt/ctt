<?php

namespace INDIGIT;

class CTT
{

    /**
     * @var \WC_Order
     */
    protected $order;

    /**
     * @var null|\SoapClient
     */
    protected $client = null;
    protected $location;

    protected $live = false;
    protected $authentication_id = null;
    protected $client_id = null;
    protected $user_id = null;
    protected $contract_id = null;
    protected $distribution_channel_id = null;
    protected $sub_product_id = null;
    protected $reference_prefix = null;
    protected $phone_number = null;

    /**
     * @var \WC_Countries
     */
    protected $countries;

    protected $request_id;
    protected $totalWeight = 0;
    protected $totalItems = 0;
    protected $zip1;
    protected $zip2;
    protected $zipShipping1;
    protected $zipShipping2;

    public function __construct(\WC_Order $order, $live, $authentication_id, $client_id, $user_id, $contract_id, $distribution_channel_id, $sub_product_id, $reference_prefix, $phone_number)
    {
        $this->order = $order;
        $this->live = $live;
        $this->authentication_id = $authentication_id;
        $this->client_id = $client_id;
        $this->user_id = $user_id;
        $this->contract_id = $contract_id;
        $this->distribution_channel_id = $distribution_channel_id;
        $this->sub_product_id = $sub_product_id;
        $this->reference_prefix = $reference_prefix;
        $this->phone_number = $phone_number;

        $this->location = $live ? 'https://portal.cttexpresso.pt/webservicecttexpresso' : 'http://cttexpressows.qa.ctt.pt/CTTEWSPool';

        $this->countries = $countries = WC()->countries;
    }

    /**
     * Create shipment for order in CTT
     *
     * @return string|null
     */
    public function createShipment()
    {
        $this->request_id = indigit()->generateUUID();

        [$this->zip1, $this->zip2] = explode('-', $this->countries->get_base_postcode());

        // shipping
        [$this->zipShipping1, $this->zipShipping2] = explode('-', $this->order->get_shipping_postcode());

        $this->totalWeight = get_post_meta($this->order->get_id(), '_cart_weight', true) * 1000; // to grams
        $this->totalItems = get_post_meta($this->order->get_id(), '_cart_quantity_sum', true);

        // If we already have references for
        $cttFilename = get_post_meta($this->order->get_id(), '_ctt_file', true);
        if ($cttFilename !== '') {
            return $cttFilename;
        }

        $payload = <<<EOL
<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:tem='http://tempuri.org/' xmlns:ctt='http://schemas.datacontract.org/2004/07/CTTExpressoWS' xmlns:ctt1='http://schemas.datacontract.org/2004/07/CTTExpressoWS.Models.ShipmentProvider' xmlns:ctt2='http://schemas.datacontract.org/2004/07/CTTExpressoWS.Models.ShipmentProvider.SEPs'>
   <soapenv:Header/>
   <soapenv:Body>
      <tem:CreateShipment>
         <tem:Input>
            <ctt:AuthenticationID>{$this->authentication_id}</ctt:AuthenticationID>
            <ctt:DeliveryNote>
               <ctt1:ClientId>{$this->client_id}</ctt1:ClientId>
               <ctt1:ContractId>{$this->contract_id}</ctt1:ContractId>
               <ctt1:DistributionChannelId>{$this->distribution_channel_id}</ctt1:DistributionChannelId>
               <ctt1:SubProductID>{$this->sub_product_id}</ctt1:SubProductID>
               <ctt1:ExtData></ctt1:ExtData>
               <ctt1:ShipmentCTT>
                <ctt1:ShipmentCTT>
                     <ctt1:HasSenderInformation>true</ctt1:HasSenderInformation>
                     <ctt1:ReceiverData>
                        <ctt1:Address>{$this->order->get_shipping_address_1()}</ctt1:Address>
                        <ctt1:City>{$this->order->get_shipping_city()}</ctt1:City>
                        <ctt1:Country>{$this->order->get_shipping_country()}</ctt1:Country>
                        <ctt1:Name>{$this->order->get_shipping_first_name()} {$this->order->get_shipping_last_name()}</ctt1:Name>
                        <ctt1:PTZipCode3>{$this->zipShipping2}</ctt1:PTZipCode3>
                        <ctt1:PTZipCode4>{$this->zipShipping1}</ctt1:PTZipCode4>
                        <ctt1:PTZipCodeLocation>{$this->order->get_shipping_city()}</ctt1:PTZipCodeLocation>
                        <ctt1:Email>{$this->order->get_billing_email()}</ctt1:Email>
                        <ctt1:Phone>{$this->order->get_billing_phone()}</ctt1:Phone>
                        <ctt1:Type>Receiver</ctt1:Type>
                     </ctt1:ReceiverData>
                     <ctt1:SenderData>
                        <ctt1:Address>{$this->countries->get_base_address()}</ctt1:Address>
                        <ctt1:City>{$this->countries->get_base_city()}</ctt1:City>
                        <ctt1:Country>{$this->countries->get_base_country()}</ctt1:Country>
                        <ctt1:Name>{$this->reference_prefix}</ctt1:Name>
                        <ctt1:PTZipCode3>{$this->zip2}</ctt1:PTZipCode3>
                        <ctt1:PTZipCode4>{$this->zip1}</ctt1:PTZipCode4>
                        <ctt1:Type>Sender</ctt1:Type>
                     </ctt1:SenderData>
                     <ctt1:ShipmentData>
                        <ctt1:ClientReference>{$this->reference_prefix}_{$this->order->get_id()}</ctt1:ClientReference>
                        <ctt1:IsDevolution>false</ctt1:IsDevolution>
                        <ctt1:Quantity>1</ctt1:Quantity>
                        <ctt1:Weight>{$this->totalWeight}</ctt1:Weight>
                     </ctt1:ShipmentData>
                  </ctt1:ShipmentCTT>
               </ctt1:ShipmentCTT>
               <ctt1:SubProductId>{$this->sub_product_id}</ctt1:SubProductId>
            </ctt:DeliveryNote>
            <ctt:RequestID>{$this->request_id}</ctt:RequestID>
            <ctt:UserID>{$this->user_id}</ctt:UserID>
         </tem:Input>
      </tem:CreateShipment>
   </soapenv:Body>
</soapenv:Envelope>
EOL;

        $location = $this->location . '/CTTShipmentProviderWS.svc?WSDL';
        $action = "http://tempuri.org/ICTTShipmentProviderWS/CreateShipment";

        try {
            indigit_log('SOAP', 'CTT REQUEST_1', ['location' => $location, 'payload' => $payload]);
            $response = $this->getSoapClient($location)->__doRequest($payload, $location, $action, 1);
            indigit_log('SOAP', 'CTT RESPONSE_1', ['response' => $response]);
        } catch (\Exception $e) {
            indigit_log('SOAP', $e, [
                'order' => $this->order->get_id()
            ]);
            return null;
        }

        $dom = new \DOMDocument();
        $dom->loadXml($response);
        $xpath = new \DOMXpath($dom);
        $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('', 'http://tempuri.org/');
        $xpath->registerNamespace('a', 'http://schemas.datacontract.org/2004/07/CTTExpressoWS');
        $xpath->registerNamespace('i', 'http://www.w3.org/2001/XMLSchema-instance');

        // Check if response is valid
        /** @var \DOMNode $status */
        $status = $xpath->query("//a:Status")->item(0);
        if (null !== $status && $status->nodeValue === 'Failure') {
            return null;
        }

        $pdf = $xpath->query("//a:File")->item(0)->nodeValue;
        $firstReference = $xpath->query("//a:FirstObject")->item(0)->nodeValue;
        $lastReference = $xpath->query("//a:LastObject")->item(0)->nodeValue;

        // Save PDF
        $directory = date('Y-m-d');
        $filename = sprintf('%s_ctt.pdf', $this->order->get_id());
        $path = sprintf('%s/%s', INDIGIT_PLG_DIR_FILES, $directory);
        !file_exists($path) && mkdir($path, 0777, true);
        $pdfFilename = sprintf('%s/%s', $path, $filename);
        file_put_contents($pdfFilename, base64_decode($pdf));

        // tracking references | request id used
        update_post_meta($this->order->get_id(), '_ctt_references', implode("||##||", [$firstReference, $lastReference, $this->request_id]));
        update_post_meta($this->order->get_id(), '_ctt_file', sprintf('%s/%s', $directory, $filename));

        $this->order->add_order_note('Pedido de transporte criado no CTTExpresso #' . $firstReference);

        return $pdfFilename;
    }

    /**
     * Get SoapClient client
     *
     * @param string $location
     * @return \SoapClient
     * @throws \SoapFault
     */
    protected function getSoapClient($location)
    {
        $this->client = new \SoapClient(null, [
            'location' => $location,
            'uri' => "",
            'use' => SOAP_LITERAL,
            'trace' => 1,
        ]);

        return $this->client;
    }
}
