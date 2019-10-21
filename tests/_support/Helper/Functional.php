<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WPBrowser;
use Codeception\Module\WPDb;
use GuzzleHttp\Client;
use TrackMage\Client\TrackMageClient;

class Functional extends Module
{
    protected $config = [
        'trackmageApi' => null,
        'buildFlavor' => '',
    ];

    /** @var TrackMageClient|null */
    private $authorizedClient;

    /**
     * @throws \Codeception\Exception\ModuleException
     */
    public function haveLoadedServerFixtures()
    {
        $client = new Client(['base_uri' => $this->config['trackmageApi'], 'http_errors' => false]);
        $content = file_get_contents(__DIR__ . '/fixtures.yaml');
        $content = str_replace('{flavor}', $this->getFlavorSlug(), $content);
        $response = $client->post('/test/load-fixtures', [
            'json' => [
                'content' => base64_encode($content)
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
        if ($response->getStatusCode() !== 201) {
            throw new ModuleException(__CLASS__, $response->getBody()->getContents());
        }
    }

    /**
     * @return string
     */
    public function getFlavorSlug()
    {
        return transliterator_transliterate(
            'Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();',
            $this->config['buildFlavor']
        );
    }

    /**
     * @return \TrackMage\Client\TrackMageClient
     * @throws \Codeception\Exception\ModuleException
     */
    public function getAuthorizedClient()
    {
        if (null !== $this->authorizedClient) {
            return $this->authorizedClient;
        }
        $client = new TrackMageClient();
        $client->setHost($this->config['trackmageApi']);
        $response = $client->getGuzzleClient()->get('/oauth/v2/token', [
            'query' => [
                'client_id' => '18165608-5a8c-4535-803f-9704792cbbd9_trackmage-public-client',
                'grant_type' => 'password',
                'username' => $this->getFlavorSlug().'@wp-plugin.tld',
                'password' => '123454',
            ],
        ]);
        $contents = $response->getBody()->getContents();
        $data = json_decode($contents, true);
        if (!isset($data['access_token'])) {
            throw new ModuleException(__CLASS__, $contents);
        }
        $client->setAccessToken($data['access_token']);

        return $this->authorizedClient = $client;
    }

    /**
     * @return array
     * @throws \Codeception\Exception\ModuleException
     * @throws \TrackMage\Client\Swagger\ApiException
     */
    public function getOAuthKeySecretPair()
    {
        $client = $this->getAuthorizedClient();
        $items = $client->getOauthClientApi()->getOauthClientCollection();
        foreach ($items as $item) {
            if (0 === strpos($item->getName(), 'wordpress-plugin')) {
                return [$item->getPublicId()[0], $item->getSecret()];
            }
        }
        throw new ModuleException(__CLASS__, 'Unable to find oauth client uploaded from fixtures');
    }

    /**
     * Get current url from WebDriver
     * @return string
     * @throws \Codeception\Exception\ModuleException
     */
    public function getCurrentUrl()
    {
        /** @var WPBrowser $module */
        $module = $this->getModule('WPBrowser');
        return $module->_getCurrentUri();
    }

    public function haveCredentialsInWordpress()
    {
        list($key, $secret) = $this->getOAuthKeySecretPair();
        /** @var WPDb $wpdb */
        $wpdb = $this->getModule('WPDb');
        $wpdb->haveOptionInDatabase('trackmage_client_id', $key);
        $wpdb->haveOptionInDatabase('trackmage_client_secret', $secret);
    }
}
