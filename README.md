# ImageOptim API PHP client

This library allows you to resize and optimize images using ImageOptim API.

ImageOptim offers [advanced compression, high-DPI/responsive image mode, and color profile support](https://imageoptim.com/features.html) that are much better than PHP's built-in image resizing functions.

## Installation

The easiest is to use [PHP Composer](https://getcomposer.org/):

```sh
composer require imageoptim/imageoptim
```

If you don't use Composer, then `require` or autoload files from the `src` directory.

## Usage

First, [register to use the API](https://im2.io/register).

```php
<?php
require "vendor/autoload.php"; // created by Composer

$api = new ImageOptim\API("🔶your api username goes here🔶");

$imageData = $api->imageFromURL('http://example.com/photo.jpg') // read this image
    ->resize(160, 100, 'crop') // optional: resize to a thumbnail
    ->dpr(2) // optional: double number of pixels for high-resolution "Retina" displays
    ->getBytes(); // perform these operations and return the image data as binary string

file_put_contents("images/photo_optimized.jpg", $imageData);
```

### Methods

#### `API($username)` constructor

    new ImageOptim\API("your api username goes here");

Creates new instance of the API. You need to give it [your username](https://im2.io/api/username).

#### `imageFromURL($url)` — source image

Creates new request that will read image from the given URL, and then resize and optimize it.

Please pass full absolute URL to images on your website.

Ideally you should supply source image at very high quality (e.g. JPEG saved at 99%), so that ImageOptim can adjust quality itself. If source images you provide are already saved at low quality, ImageOptim will not be able to make them look better.

#### `resize($width, $height = optional, $fit = optional)` — desired dimensions

* `resize($width)` — sets maximum width for the image, so it'll be resized to this width. If the image is smaller than this, it won't be enlarged.

* `resize($width, $height)` — same as above, but image will also have height same or smaller. Aspect ratio is always preserved.

* `resize($width, $height, 'crop')` — resizes and crops image exactly to these dimensions.

If you don't call `resize()`, then the original image size will be preserved.

[See options reference](https://im2.io/api/post#options) for more resizing options.

#### `dpr($x)` — pixel doubling for responsive images (HTML `srcset`)

The default is `dpr(1)`, which means image is for regular displays, and `resize()` does the obvious thing you'd expect.

If you set `dpr(2)` then pixel width and height of the image will be *doubled* to match density of "2x" displays. This is better than `resize($width*2)`, because it also adjusts sharpness and image quality to be optimal for high-DPI displays.

[See options reference](https://im2.io/api/post#opt-2x) for explanation how DPR works.

#### `quality($preset)` — if you need even smaller or extra sharp images

Quality is set as a string, and can be `low`, `medium` or `high`. The default is `medium` and should be good enough for most cases.

#### `getBytes()` — get the resized image

Makes request to ImageOptim API and returns optimized image as a string. You should save that to your server's disk.

ImageOptim performs optimizations that sometimes may take a few seconds, so instead of converting images on the fly on every request, you should convert them once and keep them.

#### `apiURL()` — debug or use another HTTPS client

Returns string with URL to `https://im2.io/…` that is equivalent of the options set. You can open this URL in your web browser to get more information about it. Or you can [make a `POST` request to it](https://im2.io/api/post#making-the-request) to download the image yourself, if you don't want to use the `getBytes()` method.

### Error handling

All methods throw on error. You can expect the following exception subclasses:

* `ImageOptim\InvalidArgumentException` means arguments to functions are incorrect and you need to fix your code.
* `ImageOptim\NetworkException` is thrown when there is problem comunicating with the API. You can retry the request.
* `ImageOptim\NotFoundException` is thrown when URL given to `imageFromURL()` returns 404. Make sure paths and urlencoding are correct. [More](https://im2.io/api/post#response).

### Help and info

See [imageoptim.com/api](https://imageoptim.com/api) for documentation and contact info. I'm happy to help!
