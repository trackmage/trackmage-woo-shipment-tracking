<?php


namespace TrackMage\WordPress\Webhook\Mappers;


class OrdersMapper implements EntityMapperInterface {

    private $entity;
    private $data;
    private $updatedFields;
    private $requestBody;

    public function __construct() {
    }

    public function supports( array $item ){
        return isset($item['entity']) && $item['entity'] == 'orders';
    }

    public function handle( array $item ) {
        // TODO: Implement handle() method.
    }

}
