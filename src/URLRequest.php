<?php

namespace ImageOptim;

class URLRequest extends Request {
    private $url;

    function __construct($username, $url) {
        parent::__construct($username);
        if (!$url) {
            throw new InvalidArgumentException("Image URL is required");
        }
        if (!preg_match('/^https?:\/\//', $url)) {
            throw new InvalidArgumentException("The API requires absolute image URL (starting with http:// or https://). Got: $url");
        }
        $this->url = $url;
    }

    function apiURL() {
        return parent::apiURL() . '/' . rawurlencode($this->url);
    }

    function getBytes() {
        return $this->getBytesWithOptions(['header' => ""], $this->url);
    }
}
