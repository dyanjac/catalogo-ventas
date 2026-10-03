<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_exposes_search_and_social_metadata_with_structured_data(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('<a class="marketing-skip" href="#contenido">', false)
            ->assertSee('<main id="contenido" tabindex="-1">', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('"applicationCategory":"BusinessApplication"', false);
    }

    public function test_registration_and_admin_login_are_not_indexed(): void
    {
        $this->get(route('saas.register.create'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('saas.register.create').'">', false);

        $this->get(route('admin.login', ['org' => 'unknown']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('<link rel="canonical" href="'.route('admin.login').'">', false);
    }

    public function test_robots_and_sitemap_publish_only_the_marketing_home(): void
    {
        $this->get(route('marketing.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('Disallow: /admin/')
            ->assertSeeText('Disallow: /saas/register')
            ->assertSeeText('Sitemap: '.route('marketing.sitemap'));

        $this->get(route('marketing.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.e(route('home')).'</loc>', false)
            ->assertDontSee('/saas/register')
            ->assertDontSee('/admin/login');
    }
}
