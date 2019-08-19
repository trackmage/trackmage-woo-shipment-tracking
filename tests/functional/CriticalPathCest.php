<?php

class CriticalPathCest
{
    public function test(\FunctionalTester $I)
    {
        $I->haveLoadedServerFixtures();
        $I->loginAsAdmin();
        $I->amOnAdminPage('/admin.php?page=trackmage');
        $I->see('Credentials');

        $this->testEmptyCredentials($I);
        $this->testAndSaveWorkingCredentials($I);
        $this->selectWorkspace($I);
    }

    private function testEmptyCredentials(\FunctionalTester $I)
    {
        //test empty credentials
        $I->sendAjaxPostRequest('/wp-admin/admin-ajax.php', ['action' => 'trackmage_test_credentials', 'clientId' => '', 'clientSecret' => '']);
        $I->canSeeResponseCodeIs(200);
        $I->canSeeResponseContains('We could not peform the check. Please try again');
    }

    private function testAndSaveWorkingCredentials(\FunctionalTester $I)
    {
        //test working credentials
        list($key, $secret) = $I->getOAuthKeySecretPair();
        $I->sendAjaxPostRequest('/wp-admin/admin-ajax.php', [
            'action' => 'trackmage_test_credentials',
            'clientId' => $key,
            'clientSecret' => $secret,
        ]);
        $I->canSeeResponseCodeIs(200);
        $I->canSeeResponseContainsJson(['status' => 'success']);

        $I->fillField('trackmage_client_id', $key);
        $I->fillField('trackmage_client_secret', $secret);
        $I->click('Save Changes');
        $I->canSee('Settings saved.');

        $I->seeOptionInDatabase(['option_name' => 'trackmage_client_id', 'option_value' => $key]);
        $I->seeOptionInDatabase(['option_name' => 'trackmage_client_secret', 'option_value' => $secret]);
    }

    private function selectWorkspace(\FunctionalTester $I)
    {
        $I->seeOptionInDatabase(['option_name' => 'trackmage_workspace', 'option_value' => '0']);

        $I->see('Workspace');
        $I->selectOption('Workspace', "fake_primary_{$I->getFlavorSlug()}_ws1");

        $workspaceId = $I->grabValueFrom('select[name=trackmage_workspace]');
        $I->click('Save Changes');
        $I->canSee('Settings saved.');

        $I->seeOptionInDatabase(['option_name' => 'trackmage_workspace', 'option_value' => $workspaceId]);
    }
}
