<?php

namespace PHPMicroTemplate\Tests;

use PHPMicroTemplate\Render;
use PHPMicroTemplate\Tests\Objects\Product;
use PHPUnit\Framework\TestCase;

/**
 * Class RenderTest
 *
 * @package PHPMicroTemplate\Tests
 */
class RenderTest extends TestCase
{
    public function testRenderTemplate(): void
    {
        $render = new Render(__DIR__ . '/Templates/');

        $products = [
            new Product('Hammer', true),
            new Product('Nails', false),
            new Product('Wood', true),
        ];

        $result = $render->renderTemplate(
            'productList.template',
            [
                'pageTitle' => 'Available products',
                'productHead' => 'Product',
                'products' => $products,
                'showVersion' => true
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/productListWithVersion', $result);

        $products = [
            new Product('Hammer', true),
            new Product('Nails', true),
            new Product('Wood', false),
        ];

        $result = $render->renderTemplate(
            'productList.template',
            [
                'pageTitle' => 'Available products',
                'productHead' => 'Product',
                'products' => $products,
                'showVersion' => false
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/productListWithoutVersion', $result);
    }
}
