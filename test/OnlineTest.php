<?php

class OnlineTest extends PHPUnit_Framework_TestCase {
    function setUp() {
        $this->api = new ImageOptim\API("gnbkrbjhzb");
    }

    public function testFullMonty() {
        $imageData = $this->api->imageFromURL('http://example.com/image.png')->resize(160,100,'crop')->dpr('2x')->getBytes();

        $gdimg = imagecreatefromstring($imageData);
        $this->assertEquals(160*2, imagesx($gdimg));
        $this->assertEquals(100*2, imagesy($gdimg));
    }

    public function testUpload() {
        $imageData = $this->api->imageFromPath(__dir__ . '/../ImageOptim.png')->resize(32)->getBytes();

        $gdimg = imagecreatefromstring($imageData);
        $this->assertEquals(32, imagesx($gdimg));
        $this->assertEquals(32, imagesy($gdimg));
    }

    /**
     * @expectedException ImageOptim\AccessDeniedException
     * @expectedExceptionCode 403
     */
    public function testBadKey() {
        $api = new ImageOptim\API("zzzzzzzz");
        $api->imageFromURL('http://example.com/image.png')->dpr('2x')->getBytes();
    }

    /**
     * @expectedException ImageOptim\OriginServerException
     * @expectedExceptionCode 403
     */
    public function testGoodKeyUpstream403() {
        $this->api->imageFromURL('https://im2.io/.htdeny')->dpr('2x')->getBytes();
    }

    /**
     * @expectedException ImageOptim\NotFoundException
     * @expectedExceptionCode 404
     */
    public function testUpstreamError() {
        $this->api->imageFromURL('http://fail.example.com/nope')->getBytes();
    }

}
