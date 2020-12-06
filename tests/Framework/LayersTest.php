<?php

namespace Osm\Tests\Framework;

use Osm\Core\App;
use Osm\Framework\Layers\Layout;
use Osm\Framework\Testing\Tests\UnitTestCase;
use Osm\Samples\Layers\Views\TestView;

class LayersTest extends UnitTestCase
{
    public function testIncludeInstruction() {
        global $osm_app; /* @var App $osm_app */

        $layout = Layout::new();

        $layout->load([
            '@include' => 'layer_test',
        ]);

        $this->assertEquals('test_root', $layout->root->id);
    }

    public function testIdSelector() {
        global $osm_app; /* @var App $osm_app */

        $layout = Layout::new();

        $layout->load('layer_test', [
            '#test_root' => [
                'modifier' => '-test-root',
            ],
        ]);

        $this->assertEquals('-test-root', $layout->root->modifier);
    }

    public function testPropertySelector() {
        global $osm_app; /* @var App $osm_app */

        $layout = Layout::new();

        $layout->load('layer_test', [
            '#test_root' => [
                'child' => TestView::new([
                    'child' => TestView::new([
                    ]),
                ]),
            ],
            '#test_root.child.child' => [
                'modifier' => '-test-child',
            ],
        ]);

        $view = $layout->root; /* @var TestView $view */

        $this->assertEquals('-test-child', $view->child->child->modifier);
    }

    public function testSelectorApi() {
        global $osm_app; /* @var App $osm_app */

        $layout = Layout::new();

        $layout->load('layer_test', [
            '#test_root' => [
                'modifier' => '-test-root',
                'child' => TestView::new([
                    'child' => TestView::new([
                        'modifier' => '-test-child',
                    ]),
                ]),
            ],
        ]);

        $this->assertEquals('-test-root', $layout->select('#test_root')->modifier);
        $this->assertEquals('-test-child', $layout->select('#test_root.child.child')->modifier);
    }
}