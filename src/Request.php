<?php

namespace ImageOptim;

class Request {
    const BASE_URL = 'https://im2.io';

    private $username, $url;
    private $width, $height, $dpr, $fit, $quality, $timeout;

    function __construct($username, $url) {
        if (!$username) throw new InvalidArgumentException();
        if (!$url) {
            throw new InvalidArgumentException("Image URL is required");
        }
        if (!preg_match('/^https?:\/\//', $url)) {
            throw new InvalidArgumentException("The API requires absolute image URL (starting with http:// or https://). Got: $url");
        }
        $this->username = $username;
        $this->url = $url;
    }

    public function resize($width, $height_or_fit = null, $fit = null) {
        if (!is_numeric($width)) {
            throw new InvalidArgumentException("Width is not a number: $width");
        }

        $width = intval($width);
        if (null === $height_or_fit) {
            $height = null;
        } else if (is_numeric($height_or_fit)) {
            $height = intval($height_or_fit);
        } else if ($fit) {
            throw new InvalidArgumentException("Height is not a number: $height_or_fit");
        } else {
            $fit = $height_or_fit;
            $height = null;
        }

        if ($width < 1 || $width > 10000) {
            throw new InvalidArgumentException("Width is out of allowed range: $width");
        }
        if ($height !== null && ($height < 1 || $height > 10000)) {
            throw new InvalidArgumentException("Height is out of allowed range: $height");
        }

        $allowedFitOptions = ['fit', 'crop', 'scale-down'];
        if (null !== $fit && !in_array($fit, $allowedFitOptions)) {
            throw new InvalidArgumentException("Fit is not one of ".implode(', ',$allowedFitOptions).". Got: $fit");
        }

        $this->width = $width;
        $this->height = $height;
        $this->fit = $fit;

        return $this;
    }

    public function timeout($timeout) {
        if (!is_numeric($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException("Timeout not a positive number: $timeout");
        }
        $this->timeout = $timeout;

        return $this;
    }

    public function dpr($dpr) {
        if (!preg_match('/^\d[.\d]*(x)?$/', $dpr, $m)) {
            throw new InvalidArgumentException("DPR should be 1x, 2x or 3x. Got: $dpr");
        }
        $this->dpr = $dpr . (empty($m[1]) ? 'x' : '');

        return $this;
    }

    public function quality($quality) {
        $allowedQualityOptions = ['low', 'medium', 'high', 'lossless'];
        if (!in_array($quality, $allowedQualityOptions)) {
            throw new InvalidArgumentException("Quality is not one of ".implode(', ',$allowedQualityOptions).". Got: $quality");
        }
        $this->quality = $quality;

        return $this;
    }

    function optimize() {
        // always. This is here to make order of calls flexible
        return $this;
    }

    function apiURL() {
        $options = [];
        if ($this->width) {
            $size = $this->width;
            if ($this->height) {
                $size .= 'x' . $this->height;
            }
            $options[] = $size;
            if ($this->fit) $options[] = $this->fit;
        } else {
            $options[] = 'full';
        }
        if ($this->dpr) $options[] = $this->dpr;
        if ($this->quality) $options[] = 'quality=' . $this->quality;
        if ($this->timeout) $options[] = 'timeout=' . $this->timeout;

        $imageURL = $this->url;
        if (preg_match('/[\s%+]/', $imageURL)) {
            $imageURL = rawurlencode($imageURL);
        }

        return self::BASE_URL . '/' . rawurlencode($this->username) . '/' . implode(',', $options) . '/' . $imageURL;
    }

    function getBytes() {
        $url = $this->apiURL();
        $stream = @fopen($url, 'r', false, stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'method' => 'POST',
                'header' => "User-Agent: ImageOptim-php/1.0 PHP/" . phpversion(),
                'timeout' => max(30, $this->timeout),
            ],
        ]));

        if (!$stream) {
            $err = error_get_last();
            throw new NetworkException("Can't send HTTPS request to: $url\n" . ($err ? $err['message'] : ''));
        }

        $res = @stream_get_contents($stream);
        if (!$res) {
            $err = error_get_last();
            fclose($stream);
            throw new NetworkException("Error reading HTTPS response from: $url\n" . ($err ? $err['message'] : ''));
        }

        $meta = @stream_get_meta_data($stream);
        if (!$meta) {
            $err = error_get_last();
            fclose($stream);
            throw new NetworkException("Error reading HTTPS response from: $url\n" . ($err ? $err['message'] : ''));
        }
        fclose($stream);

        if (!$meta || !isset($meta['wrapper_data'], $meta['wrapper_data'][0])) {
            throw new NetworkException("Unable to read headers from HTTP request to: $url");
        }
        if (!empty($meta['timed_out'])) {
            throw new NetworkException("Request timed out: $url", 504);
        }

        if (!preg_match('/HTTP\/[\d.]+ (\d+) (.*)/', $meta['wrapper_data'][0], $status)) {
            throw new NetworkException("Unexpected response: ". $meta['wrapper_data'][0]);
        }

        $code = intval($status[1]);
        if ($code >= 500) {
            throw new APIException($status[2], $code);
        }
        if ($code == 404) {
            throw new NotFoundException("Could not find the image: {$this->url}", $code);
        }
        if ($code == 403) {
            throw new AccessDeniedException("API username was not accepted: {$this->username}", $code);
        }
        if ($code >= 400) {
            throw new InvalidArgumentException($status[2], $code);
        }

        return $res;
    }
}
