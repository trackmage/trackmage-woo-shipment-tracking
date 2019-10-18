<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use WC_Order;

class OrdersMapper extends AbstractMapper {


    protected $map = [
        "id"                =>  "trackmage_order_id",
        //"orderNumber"       =>  "id",
        //"externalSource"    =>  "wp-5d9da5faf010c",
        "externalSyncId"    =>  "id",
        "status"            =>  "status",
        "subtotal"          =>  "",
        "total"             =>  "",
        "orderType"         =>  "customer",
        "fulfillmentSource" =>  "",
        "createdAt"         =>  "2019-10-18T12:25:55+03:00",
        "updatedAt"         =>  "2019-10-18T09:26:54+00:00",
        "shipments"         => [],
        "shippingAddress"   =>  [
            "addressLine1"      =>  "Valchenko 19/12",
            "addressLine2"      =>  "",
            "city"              =>  "Tampa",
            "company"           =>  "",
            "country"           =>  "United States",
            "countryIso2"       =>  "US",
            "firstName"         =>  "Yev",
            "lastName"          =>  "Harb",
            "postcode"          =>  "32156",
            "state"             =>  "Florida"
        ],
        "billingAddress"    => [
            "addressLine1"      =>  "Valchenko 19/12",
            "addressLine2"      =>  "",
            "city"              =>  "Tampa",
            "company"           =>  "",
            "country"           =>  "United States",
            "countryIso2"       =>  "US",
            "firstName"         =>  "Yev",
            "lastName"          =>  "Harb",
            "postcode"          =>  "32156",
            "state"             =>  "Florida"
        ]
    ];

    /**
     * OrdersMapper constructor.
     *
     * @param null $source
     */
    public function __construct($source = null) {
        $this->source = $source;
    }

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

            if(!$this->canHandle() || $trackMageId != $trackmage_order_id)
                return null;

            $data = $this->prepareData();

            //$this->entity = $this->repo->update($data, ['id' => $shipmentId]);

            return $this->entity;
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update order from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
