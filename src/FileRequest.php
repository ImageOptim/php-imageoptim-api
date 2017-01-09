<?php

namespace ImageOptim;

class FileRequest extends Request {
    private $path;

    function __construct($username, $path) {
        parent::__construct($username);
        if (!$path) {
            throw new InvalidArgumentException("Image path is required");
        }
        if (!file_exists($path)) {
            $cwd = getcwd();
            throw new InvalidArgumentException("The file '$path' could not be found (searched relative to '$cwd')");
        }
        $this->path = $path;
    }

    function apiURL() {
        return parent::apiURL();
    }

    function getBytes() {
        $fileData = @file_get_contents($this->path);
        if (!$fileData) {
            throw new APIException("Unable to read {$this->path}");
        }

        $contentHash = md5($this->path);
        $boundary = "XXX$contentHash";
        $nameEscaped = addslashes(basename($this->path));

        $url = $this->apiURL();
        $content = "--$boundary\r\n" .
                   "Content-Disposition: form-data; name=\"file\"; filename=\"{$nameEscaped}\"\r\n" .
                   "Content-Type: application/octet-stream\r\n" .
                   "Content-Transfer-Encoding: binary\r\n" .
                   "\r\n$fileData\r\n--$boundary--";

        return $this->getBytesWithOptions([
            'header' => "Content-Length: " . strlen($content) . "\r\n" .
                        "Content-MD5: $contentHash\r\n" .
                        "Content-Type: multipart/form-data, boundary=$boundary\r\n",
            'content' => $content,
        ], $this->path);
    }
}
