<?php


namespace TrackMage\WordPress\Webhook\Mappers;


class ShipmentsMapper implements EntityMapperInterface {

    private $entity;

    private $data;

    private $updatedFields;

    private $requestBody;


    public function __construct() {
    }

    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] == 'shippments';
    }

    public function handle( array $item ) {
        // TODO: Implement handle() method.
    }

}
