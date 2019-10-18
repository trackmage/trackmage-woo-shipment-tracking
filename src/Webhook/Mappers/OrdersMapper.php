<?php


namespace TrackMage\WordPress\Webhook\Mappers;


class OrdersMapper extends AbstractMapper {


    public function __construct() {
    }

    public function supports( array $item ){
        return isset($item['entity']) && $item['entity'] == 'orders';
    }

    public function handle( array $item ) {
        // TODO: Implement handle() method.
    }

}
