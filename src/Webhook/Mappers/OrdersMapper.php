<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use WC_Order;
use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Exception\InvalidArgumentException;

class OrdersMapper extends AbstractMapper {


    protected $map = [
        "orderStatus"            =>  [
            "title"                => "",
            "code"              => "status"
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
        return isset($item['entity']) && $item['entity'] === 'orders';
    }

    /**
     * Handle updates for order from TrackMage to local
     *
     * @param array $item
     */
    public function handle( array $item ) {
        $this->data = isset( $item['data'] ) ? $item['data'] : [];
        if ( empty( $this->data ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because data is empty' );
        }
        $this->updatedFields = isset( $item['updatedFields'] ) ? $item['updatedFields'] : [];
        if ( empty( $this->updatedFields ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there are no updated fields' );
        }

        $trackMageId = $this->data['id'];
        if ( empty( $trackMageId ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there is no TrackMage Id' );
        }

        $orderId = isset( $this->data['externalSyncId'] ) ? $this->data['externalSyncId'] : '';
        if ( empty( $orderId ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there is no externalSyncId' );
        }

        $this->entity = wc_get_order( $orderId );
        $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );

        $this->validateData();

        if($trackMageId !== $trackmage_order_id)
            throw new EndpointException('Unable to handle order because TrackMageId is different');

        try {
            foreach ($this->updatedFields as $field){
                if($field == 'orderStatus'){
                    $this->entity->update_status($this->getWpStatus($this->data['orderStatus']['code']));
                }else{
                    $parts = explode('.', $field);
                    if(isset($parts[0]) && isset($this->map[$parts[0]]) && isset($parts[1]) && isset($this->map[$parts[0]][$parts[1]])){
                        update_post_meta($orderId, $this->map[$parts[0]][$parts[1]], $this->data[$parts[0]][$parts[1]]);
                    }
                }
            }
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during updating order from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function validateData() {
        // check if workspace is correct
        if(!isset($this->data['workspace']) || "/workspaces/".$this->workspace !== $this->data['workspace'])
            throw new InvalidArgumentException('Unable to handle because workspace is not correct');

        return parent::validateData();
    }

    /**
     * @return string|null
     */
    private function getWpStatus($tmStatus)
    {
        $usedAliases = get_option( 'trackmage_order_status_aliases', [] );
        return ($res = array_search($tmStatus, $usedAliases))?str_replace('wp-','',$res):null;
    }
}
