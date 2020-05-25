<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

trait GuzzleMockTrait
{
    /**
     * @param Response[]|null $responses
     * @param Request[]|null $requests
     * @return Client
     */
    private function createClient(array $responses = null, array &$requests = null)
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        if ($requests !== null) {
            $handler->push(Middleware::history($requests));
        }
        $client = new Client([
            'handler' => $handler,
            'http_errors' => true,
        ]);
        return $client;
    }

    /**
     * @param array|array[] $requests
     * @param string[] $expectedList Array<Array<$expectedMethod, $expectedPath, $expectedQuery>>
     */
    private function assertMethodsWereCalled(array $requests, $expectedList)
    {
        if (!isset($requests[0])) {
            $requests = [$requests];
        }
        $actualList = array_map(function (Request $request) {
            $query = $this->parseQuery($request->getUri()->getQuery());
            return [$request->getMethod(), $request->getUri()->getPath(), $query];
        }, array_column($requests, 'request'));
        self::assertEquals(count($expectedList), count($actualList));
        foreach ($expectedList as $key => $expectedItem) {
            list($expectedMethod, $expectedPath) = $expectedItem;
            $expectedQuery = isset($expectedItem[2]) ? $expectedItem[2] : null;
            self::assertEquals($expectedMethod, $actualList[$key][0]);
            self::assertRegExp('@' . $expectedPath . '@', $actualList[$key][1]);
            if (null !== $expectedQuery) {
                self::assertArraySubset($expectedQuery, $actualList[$key][2]);
            }
        }
    }

    /**
     * parse_str() converts dots and spaces to underscores
     * @param $data
     * @return array|false
     */
    private function parseQuery($data)
    {
        $data = preg_replace_callback('/(?:^|(?<=&))[^=[]+/', function($match) {
            return bin2hex(urldecode($match[0]));
        }, $data);

        parse_str($data, $values);

        return array_combine(array_map('hex2bin', array_keys($values)), $values);
    }

    private function splitRequest(Request $request)
    {
        $parts = $request->getUri();
        parse_str($parts->getQuery(), $queries);
        return array($parts->getPath(), $queries);
    }

    private function assertSubmittedForm(array $expected, Request $request)
    {
        parse_str($request->getBody()->getContents(), $actual);
        self::assertEquals($expected, $actual);
    }

    private function assertSubmittedJsonEquals(array $expected, Request $request)
    {
        $actual = json_decode($request->getBody()->getContents(), true);
        self::assertEquals($expected, $actual);
    }

    private function assertSubmittedJsonIncludes(array $expected, Request $request)
    {
        $actual = json_decode($request->getBody()->getContents(), true);
        self::assertArraySubset($expected, $actual);
    }

    private function createBadResponse($status = 400, $body = null)
    {
        $request = new Request('GET', '');
        $response = new Response($status, [], $body);
        if ($status >= 500) {
            return new ServerException('error', $request, $response);
        }
        return new ClientException('error', $request, $response);
    }

    private function createRequestException()
    {
        $request = new Request('GET', '');
        return new RequestException('error', $request);
    }

    private function createConnectionTimeoutResponse()
    {
        $request = new Request('GET', '');
        return new ConnectException('Connection timeout', $request);
    }

    private function createJsonResponse($status = 200, array $data = [])
    {
        return new Response($status, [], json_encode($data));
    }

    private function createEmptyResponse($status = 200)
    {
        return new Response($status);
    }

    private function assertRequestContentType(Request $request, $expected)
    {
        $content = $request->getHeaderLine('content-type');
        self::assertEquals($expected, $content);
    }


    private static function createProductVariation( $product_id, $variation_data ){
        // Get the Variable product object (parent)
        $product = wc_get_product($product_id);

        $variation_post = array(
            'post_title'  => $product->get_name(),
            'post_name'   => 'product-'.$product_id.'-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'guid'        => $product->get_permalink()
        );

        // Creating the product variation
        $variation_id = wp_insert_post( $variation_post );

        // Get an instance of the WC_Product_Variation object
        $variation = new WC_Product_Variation( $variation_id );

        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute => $term_name )
        {
            $taxonomy = 'pa_'.$attribute; // The attribute taxonomy

            // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
            if( ! taxonomy_exists( $taxonomy ) ){
                register_taxonomy(
                    $taxonomy,
                    'product_variation',
                    array(
                        'hierarchical' => false,
                        'label' => ucfirst( $attribute ),
                        'query_var' => true,
                        'rewrite' => array( 'slug' => sanitize_title($attribute) ), // The base slug
                    )
                );
            }

            // Check if the Term name exist and if not we create it.
            if( ! term_exists( $term_name, $taxonomy ) )
                wp_insert_term( $term_name, $taxonomy ); // Create the term

            $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug

            // Get the post Terms names from the parent variable product.
            $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

            // Check if the post term exist and if not we set it in the parent variable product.
            if( ! in_array( $term_name, $post_term_names ) )
                wp_set_post_terms( $product_id, $term_name, $taxonomy, true );

            // Set/save the attribute data in the product variation
            update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );
        }

        ## Set/save all other data

        // SKU
        if( ! empty( $variation_data['sku'] ) )
            $variation->set_sku( $variation_data['sku'] );

        // Prices
        if( empty( $variation_data['sale_price'] ) ){
            $variation->set_price( $variation_data['regular_price'] );
        } else {
            $variation->set_price( $variation_data['sale_price'] );
            $variation->set_sale_price( $variation_data['sale_price'] );
        }
        $variation->set_regular_price( $variation_data['regular_price'] );

        // Stock
        if( ! empty($variation_data['stock_qty']) ){
            $variation->set_stock_quantity( $variation_data['stock_qty'] );
            $variation->set_manage_stock(true);
            $variation->set_stock_status('');
        } else {
            $variation->set_manage_stock(false);
        }

        $variation->set_weight(''); // weight (reseting)

        $variation->save(); // Save the data

        return $variation;
    }
}
