<?php


namespace TrackMage\WordPress\Repository;


use GuzzleHttp\Exception\ClientException;
use TrackMage\Client\TrackMageClient;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\EntitySyncInterface;

class AbstractApiRepository {

    /**
     * @var string
     */
    protected $apiEndpoint;

    private $entitySync;

    public function __construct(EntitySyncInterface $entitySync) {
        $this->entitySync = $entitySync;
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function find($id)
    {
        try {
            $client   = Plugin::get_client();
            $response = $client->get( $this->apiEndpoint . "/{$id}");
            return TrackMageClient::item($response);
        } catch ( ClientException $e ) {
            error_log('Unable to find: '.TrackMageClient::error($e));
            return null;
        }
    }

    /**
     *
     * @param array $criteria
     * @param int|null $limit
     * @return array|null
     */
    public function findBy(array $criteria, $limit = null)
    {
        try {
            $client   = Plugin::get_client();
            $criteria['workspace'] = get_option( 'trackmage_workspace' );
            if(!empty($limit)) {
                $criteria['page'] = 1;
                $criteria['itemsPerPage'] = $limit;
            }
            $response = $client->get( $this->apiEndpoint, [
                'query' => $criteria
            ] );
            return TrackMageClient::collection($response);
        } catch ( ClientException $e ) {
            error_log('Unable to findBy: '.TrackMageClient::error($e));
            return null;
        }
    }

    /**
     * @param array $data
     * @return array|null
     */
    public function insert(array $data)
    {
        return $this->entitySync->sync($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @return array|null
     */
    public function update($id, array $data)
    {
        $data['id'] = $id;
        return $this->entitySync->sync($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @return array|null
     */
    public function delete($id)
    {
        return $this->entitySync->delete($id);
    }

    /**
     * @param array $criteria
     * @return array|null
     */
    public function findOneBy(array $criteria)
    {
        $items = $this->findBy($criteria, 1);
        return end($items);
    }
}
