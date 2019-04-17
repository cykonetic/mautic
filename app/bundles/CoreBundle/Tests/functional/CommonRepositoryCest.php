<?php

namespace Mautic\CoreBundle;

class CommonRepositoryCest
{
    // tests
    public function ensureIsMineSearchCommandDoesntCauseExceptionDueToBadDQL(FunctionalTester $I)
    {
        $I->amHttpAuthenticated('admin', 'mautic');
        $I->amOnPage('/s/contacts');
        $I->sendAjaxGetRequest('/s/contacts', ['search' => 'is:mine']);

        $I->seeResponseCodeIs(200);
        $I->seeInSource('is:mine');
    }
}
