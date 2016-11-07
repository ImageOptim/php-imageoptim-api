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

There's a longer example at the end of the readme.

### Methods

#### `API($username)` constructor

    new ImageOptim\API("your api username goes here");

Creates new instance of the API. You need to give it [your username](https://im2.io/api/username).

#### `imageFromPath($filePath)` — local source image

Creates a new request that will [upload](https://im2.io/api/upload) the image to the API, and then resize and optimize it. The upload method is necessary for optimizing files that are not on the web (e.g. `localhost`, files in `/tmp`).

For images that have a public URLs (e.g. published on a website) it's faster to use the URL method instead:

#### `imageFromURL($url)` — remote source image

Creates a new request that will read the image from the given public URL, and then resize and optimize it.

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
* `ImageOptim\NotFoundException` is thrown when URL given to `imageFromURL()` returned 404. Make sure paths and urlencoding are correct. [More](https://im2.io/api/post#response).
* `ImageOptim\OriginServerException` is thrown when URL given to `imageFromURL()` returned 4xx or 5xx error. Make sure your server allows access to the file.

If you're writing a script that processes a large number of images in one go, don't launch it from a web browser, as it will likely time out. It's best to launch such scripts via CLI (e.g. via SSH).

### Help and info

See [imageoptim.com/api](https://imageoptim.com/api) for documentation and contact info. I'm happy to help!

### Example

This is a script that optimizes an image. Such script usually would be ran when a new image is uploaded to the server. You don't need to run any PHP code to *serve* optimized images.

The API operates on a single image at a time. When you want to generate multiple image sizes/thumbnails, repeat the whole procedure for each image at each size.

```php
<?php

// This line is required once to set up Composer
// If this file can't be found, try changing the path
// and or run `composer update` in your project's directory
require "vendor/autoload.php";

$api = new ImageOptim\API("🔶your api username goes here🔶");

// imageFromURL/imageFromPath creates a temporary object used to store
// settings of the optimization.
$imageParams = $api->imageFromURL('http://example.com/photo.jpg');

// You set various settings on this object (or none to get the defaults).
$imageParams->quality('low');
$imageParams->resize(1024);

// Next, to start the optimizations and get the optimized image, call:
$imageData = $imageParams->getBytes();

/*
 the getBytes() call may take a while to run, so it's intended to be
 called only once per image (e.g. only when a new image is uploaded
 to your server). If you'd like to "lazily" optimize arbitrary images
 on-the-fly when  they're requested, there is a better API for that:
 https://im2.io/api/get
*/

// Save the image data somewhere on the server, e.g.
file_put_contents("images/photo_optimized.jpg", $imageData);

// Note that this script only prepares a static image file
// (in this example in images/photo_optimized.jpg),
// and does not serve it to the browser. Once the optimized
// image is saved to disk you should serve it normally
// as you'd do with any regular image file.

```

