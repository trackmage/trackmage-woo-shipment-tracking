<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use WC_Order;

class OrdersMapper extends AbstractMapper {


    protected $map = [
        "status"            =>  [
            "id"                => "",
            "name"              => "status"
        ],
        "shippingAddress"   =>  [
            "addressLine1"      =>  "_shipping_address_1",
            "addressLine2"      =>  "_shipping_address_2",
            "company"           =>  "_shipping_company",
            "city"              =>  "_shipping_city",
            "countryIso2"       =>  "_shipping_country",
            "firstName"         =>  "_shipping_first_name",
            "lastName"          =>  "_shipping_last_name",
            "postcode"          =>  "_shipping_postcode",
            "state"             =>  "_shipping_state" // full name to code
        ],
        "billingAddress"    => [
            "addressLine1"      =>  "_billing_address_1",
            "addressLine2"      =>  "_billing_address_2",
            "city"              =>  "_billing_city",
            "company"           =>  "_billing_company",
            "countryIso2"       =>  "_billing_country",
            "firstName"         =>  "_billing_first_name",
            "lastName"          =>  "_billing_last_name",
            "postcode"          =>  "_billing_postcode",
            "state"             =>  "_billing_state" // full name to code
        ]
    ];

    /**
     * @param array $item
     *
     * @return bool
     */
    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] == 'orders';
    }

    /**
     * Handle updates for order from TrackMage to local
     *
     * @param array $item
     */
    public function handle( array $item ) {
        try {
            $this->data = $item['data'];
            $this->updatedFields = $item['updatedFields'];
            $orderId = $this->data['externalSyncId'];
            $trackMageId = $this->data['id'];

            //$this->loadEntity($shipmentId, $trackMageId);

            $this->entity = wc_get_order( $orderId );
            $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );

            if(!$this->canHandle() || $trackMageId !== $trackmage_order_id)
                throw new InvalidArgumentException('Order cannot be updated: '. $orderId);

            //$data = $this->prepareData();

            //$this->entity = $this->repo->update($data, ['id' => $shipmentId]);
            foreach ($this->updatedFields as $field){
                if($field == 'status'){
                    $this->entity->update_status($this->data['status']['name']);
                }else{
                    $parts = explode('.', $field);
                    if(isset($this->map[$parts[0]])){
                        update_post_meta($orderId, $this->map[$parts[0]][$parts[1]], $this->data[$parts[0]][$parts[1]]);
                    }
                }
            }

        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update order from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array
     */
    private function updateShippingAddress(WC_Order $order)
    {
        $countryIso2 = $order->get_shipping_country();
        $stateCode = $order->get_billing_state();
        $state = $this->getState($countryIso2, $stateCode);

        return [
            'addressLine1' => $order->get_shipping_address_1(),
            'addressLine2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'company' => $order->get_shipping_company(),
            'countryIso2' => $countryIso2,
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'postcode' => $order->get_shipping_postcode(),
            'state' => $state,
        ];
    }

    /**
     * @return array
     */
    private function updateBillingAddress(WC_Order $order)
    {
        $countryIso2 = $order->get_billing_country();
        $stateCode = $order->get_billing_state();
        $state = $this->getState($countryIso2, $stateCode);

        return [
            'addressLine1' => $order->get_billing_address_1(),
            'addressLine2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'company' => $order->get_billing_company(),
            'countryIso2' => $countryIso2,
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'postcode' => $order->get_billing_postcode(),
            'state' => $state,
        ];
    }

    /**
     * Converts the WC state code to name. Example: CN-1 to Beijing / 北京
     * @param string|null $countryIso2
     * @param string|null $stateCode
     * @return string|null
     */
    private function getState($countryIso2, $stateCode)
    {
        if (empty($countryIso2) || empty($stateCode)) {
            return null;
        }
        $states = WC()->countries->get_states($countryIso2);
        if (!isset($states[$stateCode])) {
            return null;
        }
        $state = $states[$stateCode];
        $state = html_entity_decode($state);
        return $state;
    }
}
