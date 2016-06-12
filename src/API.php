<?php

namespace ImageOptim;

class API {
    private $username;

    function __construct($username) {
        if (empty($username) || !is_string($username) || false !== strpos($username, ' ')) {
            throw new InvalidArgumentException("First argument to ImageOptim\\API must be the username\nGet your username from https://im2.io/register\n");
        }
        $this->username = $username;
    }

    function imageFromURL($url) {
        return new URLRequest($this->username, $url);
    }

    function imageFromPath($file) {
        return new FileRequest($this->username, $file);
    }
}
