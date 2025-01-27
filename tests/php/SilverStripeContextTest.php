<?php

namespace SilverStripe\BehatExtension\Tests;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Selector\SelectorsHandler;
use Behat\Mink\Session;
use Behat\Mink\Mink;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Element\Element;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\BehatExtension\Tests\SilverStripeContextTest\FeatureContext;
use SilverStripe\Dev\SapphireTest;

class SilverStripeContextTest extends SapphireTest
{

    protected $backupGlobals = false;

    protected bool $doSetSupportedModuleLocaleToUS = false;

    public function testGetRegionObjThrowsExceptionOnUnknownSelector()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Cannot find 'region_map' in the behat.yml");
        $context = $this->getContextMock();
        $context->getRegionObj('.unknown');
    }

    public function testGetRegionObjThrowsExceptionOnUnknownRegion()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Cannot find the specified region in the behat.yml");
        $context = $this->getContextMock();
        $context->setRegionMap(array('MyRegion' => '.my-region'));
        $context->getRegionObj('.unknown');
    }

    public function testGetRegionObjFindsBySelector()
    {
        $context = $this->getContextMock();
        $context->getSession()->getPage()
            ->expects($this->any())
            ->method('find')
            ->will($this->returnValue($this->getElementMock()));
        $obj = $context->getRegionObj('.some-selector');
        $this->assertNotNull($obj);
    }

    public function testGetRegionObjFindsByRegion()
    {
        $context = $this->getContextMock();
        $el = $this->getElementMock();
        $context->getSession()->getPage()
            ->expects($this->any())
            ->method('find')
            ->will($this->returnCallback(function ($type, $selector) use ($el) {
                return ($selector == '.my-region') ? $el : null;
            }));
        $context->setRegionMap(array('MyRegion' => '.my-asdf'));
        $obj = $context->getRegionObj('.my-region');
        $this->assertNotNull($obj);
    }

    /**
     * @return FeatureContext
     */
    protected function getContextMock()
    {
        $pageMock = $this->getMockBuilder(DocumentElement::class)
            ->disableOriginalConstructor()
            ->setMethods(array('find'))
            ->getMock();
        $sessionMock = $this->getMockBuilder(Session::class)
            ->setConstructorArgs(array(
                $this->getMockBuilder(DriverInterface::class)->getMock(),
                $this->getMockBuilder(SelectorsHandler::class)->getMock()
            ))
            ->setMethods(array('getPage'))
            ->getMock();
        $sessionMock->expects($this->any())
            ->method('getPage')
            ->will($this->returnValue($pageMock));
        $mink = new Mink(array('default' => $sessionMock));
        $mink->setDefaultSessionName('default');

        $context = new FeatureContext(array());
        $context->setMink($mink);

        return $context;
    }

    /**
     * @return Element|MockObject
     */
    protected function getElementMock()
    {
        return $this->getMockBuilder(Element::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
