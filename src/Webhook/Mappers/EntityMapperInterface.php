<?php


namespace TrackMage\WordPress\Webhook\Mappers;


interface EntityMapperInterface {

    public function supports(array $item);

    public function handle(array $item);

}
