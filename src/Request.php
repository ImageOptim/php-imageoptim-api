<?php

namespace ImageOptim;

abstract class Request {
    const BASE_URL = 'https://im2.io';

    private $username;
    private $width, $height, $dpr, $fit, $bgcolor, $quality, $timeout;

    function __construct($username) {
        if (!$username) throw new InvalidArgumentException();
        $this->username = $username;
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

        $allowedFitOptions = ['fit', 'crop', 'scale-down', 'pad'];
        if (null !== $fit && !in_array($fit, $allowedFitOptions)) {
            throw new InvalidArgumentException("Fit is not one of ".implode(', ',$allowedFitOptions).". Got: $fit");
        }

        if (!$height && ('pad' === $fit || 'crop' === $fit)) {
            throw new InvalidArgumentException("Height is required for '$fit' scaling mode\nPlease specify height or use 'fit' scaling mode to allow flexible height");
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

    public function bgcolor($background_color) {
        if ('transparent' === $background_color || false === $background_color || null === $background_color) {
            $this->bgcolor = null;
        } else if (is_string($background_color) && preg_match('/^#?([0-9a-f]+)$/i', $background_color, $m)) {
            $this->bgcolor = $m[1];
        } else {
            throw new InvalidArgumentException("Background color must be a hex string (e.g. AABBCC). Got: $background_color");
        }
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

    protected function apiURL() {
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
        if ($this->bgcolor) $options[] = 'bgcolor=' . $this->bgcolor;

        return self::BASE_URL . '/' . rawurlencode($this->username) . '/' . implode(',', $options);
    }

    protected function getBytesWithOptions(array $options, $sourceURL) {
        $url = $this->apiURL();
        $options['timeout'] = max(30, $this->timeout);
        $options['ignore_errors'] = true;
        $options['method'] = 'POST';
        $options['header'] .= "Accept: image/*,application/im2+json\r\n" .
                              "User-Agent: ImageOptim-php/1.1 PHP/" . phpversion();

        $stream = @fopen($url, 'r', false, stream_context_create(['http'=>$options]));

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

        $status = intval($status[1]);
        $errorMessage = $status[2];

        if ($res && preg_grep('/content-type:\s*application\/im2\+json/i', $meta['wrapper_data'])) {
            $json = @json_decode($res);
            if ($json) {
                if (isset($json->status)) {
                    $status = $json->status;
                }
                if (isset($json->error)) {
                    $errorMessage = $json->error;
                }
                if (isset($json->code) && $json->code === 'IM2ACCOUNT') {
                    throw new AccessDeniedException($errorMessage, $status);
                }
            }
        }

        if ($status >= 500) {
            throw new APIException($errorMessage, $status);
        }
        if ($status == 404) {
            throw new NotFoundException("Could not find the image: {$sourceURL}", $status);
        }
        if ($status == 403) {
            throw new OriginServerException("Origin server denied access to {$sourceURL}", $status);
        }
        if ($status >= 400) {
            throw new InvalidArgumentException($errorMessage, $status);
        }

        return $res;
    }
}
