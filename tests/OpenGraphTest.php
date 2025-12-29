<?php

namespace TractorCow\OpenGraph\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use TractorCow\OpenGraph\OpenGraph;

class OpenGraphTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()
            ->set(OpenGraph::class, 'application_id', 'SiteConfig')
            ->set(OpenGraph::class, 'admin_id', 'SiteConfig')
            ->set(OpenGraph::class, 'default_tagbuilder', 'TractorCow\OpenGraph\ObjectBuilders\OpenGraphBuilder');
    }

    public function testConfig(): void
    {
        $this->assertEquals('SiteConfig', OpenGraph::get_config('application_id'));
        $this->assertEquals('SiteConfig', OpenGraph::get_config('admin_id'));
        $this->assertEquals('TractorCow\OpenGraph\ObjectBuilders\OpenGraphBuilder', OpenGraph::get_default_tagbuilder());
    }
}
