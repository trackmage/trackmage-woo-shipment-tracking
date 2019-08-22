<?php

namespace TrackMage\WordPress\Syncrhonization;

interface EntitySyncInterface
{
    public function sync($id);

    public function delete($id);
}
