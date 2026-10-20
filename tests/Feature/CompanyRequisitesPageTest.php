<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompanyRequisitesPageTest extends TestCase
{
    public function test_public_requisites_page_uses_canonical_company_data(): void
    {
        $company = config('legal.commercial_documents');

        $this->get(route('company.requisites'))
            ->assertOk()
            ->assertSeeText('Реквизиты ООО «СанБизнесГруп»')
            ->assertSeeText($company['unp'])
            ->assertSeeText($company['iban'])
            ->assertSeeText($company['bank'])
            ->assertSeeText($company['legal_address'])
            ->assertSee('data-copy-all', false)
            ->assertSee('window.print()', false);
    }

    public function test_footer_contains_quick_link_to_requisites(): void
    {
        $this->get('/contacts')
            ->assertOk()
            ->assertSee(route('company.requisites'), false)
            ->assertSeeText('Реквизиты компании');
    }
}
