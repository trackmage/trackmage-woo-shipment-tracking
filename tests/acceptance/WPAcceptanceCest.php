<?php

/**
 * This is example of an Acceptance Test
 * TODO: remove if not needed
 */
class WPAcceptanceCest
{
    /**
     * It should be able to reach the homepage
     *
     * @test
     */
    public function should_be_able_to_reach_the_homepage( \AcceptanceTester $I ) {
        $I->havePostInDatabase(['post_title' => 'A test post']);
        $I->amOnPage( '/' );
        $I->see('A test post');
    }
}
