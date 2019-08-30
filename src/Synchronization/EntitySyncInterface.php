<?php

namespace TrackMage\WordPress\Synchronization;

interface EntitySyncInterface
{
    public function sync($id);

    public function delete($id);
}
