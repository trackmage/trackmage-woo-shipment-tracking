<?php

use Codeception\TestCase\WPTestCase;

/**
 * This is example of an Integration Test
 * TODO: remove if not needed
 */
class WPUnitTest extends WPTestCase {

    /**
     * @test
     * it should render the shortcode
     */
    public function it_should_render_the_shortcode(){

        // GIVEN / ARRANGE
        $shortCode = "[first-testable-shortcode]";

        // WHEN / ACT
        $returnedContent = do_shortcode($shortCode);

        // THEN / ASSERT
        $this->assertStringContainsString('Hello World!', $returnedContent);
    }

}

function helloWorld(){
    return "Hello World!";
}
add_shortcode( 'first-testable-shortcode', 'helloWorld' );
