<?php


namespace TrackMage\WordPress\Repository;


use GuzzleHttp\Exception\ClientException;
use TrackMage\Client\Swagger\ApiException;
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
            $response = $client->getGuzzleClient()->get( $this->apiEndpoint . "/{$id}");
            $contents = $response->getBody()->getContents();
            return json_decode( $contents, true );
        } catch ( ApiException $e ) {
            return null;
        } catch ( ClientException $e ) {
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
            $response = $client->getGuzzleClient()->get( $this->apiEndpoint, [
                'query' => $criteria
            ] );
            $contents = $response->getBody()->getContents();
            $data     = json_decode( $contents, true );
            return isset( $data['hydra:member'] ) ? $data['hydra:member'] : [];
        } catch ( ApiException $e ) {
            return null;
        } catch ( ClientException $e ) {
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
