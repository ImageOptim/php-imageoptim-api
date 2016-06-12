<?php

class BasicTest extends PHPUnit_Framework_TestCase {

    function setUp() {
        $this->api = new ImageOptim\API("testtest");
    }

    /**
     * @expectedException \ImageOptim\InvalidArgumentException
     */
    public function testRequiresUsername1() {
        new ImageOptim\API([]);
    }

    /**
     * @expectedException \ImageOptim\InvalidArgumentException
     * @expectedExceptionMessage username
     */
    public function testRequiresUsername2() {
        new ImageOptim\API(null);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage URL
     */
    public function testNeedsURL() {
        $this->api->imageFromURL('local/path.png');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage could not be found
     */
    public function testNeedsPath() {
        $this->api->imageFromPath('http://nope/path.png');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Width
     */
    public function testResizeWidth() {
        $this->api->imageFromURL('http://example.com')->resize("bad");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Height
     */
    public function testResizeBadHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, "bad", "crop");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Height
     */
    public function testResizeNegativeHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, -1, "crop");
    }

    public function testResizeWithoutHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, "fit");
    }

    public function testResizeWithHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, 100, "crop");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Fit
     */
    public function testResizeInvalidKeyword() {
        $this->api->imageFromURL('http://example.com')->resize(320, 100, "loose");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Height
     */
    public function testCropNeedsHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, null, "crop");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Height
     */
    public function testPadNeedsHeight() {
        $this->api->imageFromURL('http://example.com')->resize(320, null, "pad");
    }

    public function testEncodesURLIfNeeded() {
        $example = 'http://example.com/%2F';
        $this->assertContains(rawurlencode($example), $this->api->imageFromURL($example)->apiURL());
    }

    public function testPad() {
        $apiurl = $this->api->imageFromURL('http://example.com')->resize(10,15,'pad')->bgcolor('#FFffFF')->apiURL();

        $this->assertInternalType('string', $apiurl);
        $this->assertContains('10x15', $apiurl);
        $this->assertContains('pad', $apiurl);
        $this->assertContains('bgcolor=FFffFF', $apiurl);
    }

    public function testChains() {
        $c1 = $this->api->imageFromURL('http://example.com')->resize(1280)->optimize()->timeout(34)
            ->quality('low')->resize(1280)->dpr('2x')->resize(1280, 300);

        $c2 = $this->api->imageFromURL('http://example.com')->optimize()->resize(1280)->resize(1280)
            ->dpr(2)->timeout(34)->resize(1280, 300)->quality('low');

        $this->assertInternalType('string', $c1->apiURL());
        $this->assertEquals($c1->apiURL(), $c2->apiURL());
        $this->assertContains('quality=low', $c2->apiURL());
        $this->assertContains('2x', $c2->apiURL());
        $this->assertContains('1280x300', $c1->apiURL());
        $this->assertContains('timeout=34', $c1->apiURL());
        $this->assertContains('/http%3A%2F%2Fexample.com', $c1->apiURL());
    }
}
