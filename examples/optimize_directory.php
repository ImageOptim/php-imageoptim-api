#!/usr/bin/env php
<?php

// This is a shell script, not a webpage.
// That's because the script will take a while to run, and it could time out in a browser.
if (php_sapi_name() != "cli") {
    @header("Content-Type: text/plain;charset=utf8");
    echo "This file is meant to be run from command line, and not via a web server. Please run it from a terminal (e.g. connect via SSH):\n";
    echo "cd ", __DIR__, "\n";
    die("php ./".basename(__FILE__)."\n");
}

// If this line fails, run "composer update".
// If you don't have the composer command, see https://getcomposer.org
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
require_once "vendor/autoload.php";

function usage() {
    echo "This command takes 4 arguments:\n";
    echo " - your ImageOptim API username (https://imageoptim.com/api/register),\n";
    echo " - maximum width of the images (optional),\n";
    echo " - source directory to read unoptimized images from,\n";
    echo " - and a destination directory to save converted images to.\n\n";
    echo "If you omit the size, images will keep their original dimensions.\n";
    echo "If you specify a size, the size will be added to images' filenames,\n";
    echo "so you can generate multiple thumbnail sizes in the same directory.\ne.g.:\n";
    // Note that paths in the example are fictional and you'll need to adjust them for your server
    echo "php ", $_SERVER['argv'][0], " exampleapikey 640 /www/example.com/originals /www/example.com/optimized\n";
    echo "The above example will read all images from /www/example.com/originals,\n";
    echo "resize them to max 640 pixels wide, and save with a '-640.jpg' suffix.\n";
    echo "/www/example.com/originals/hello.png -> /www/example.com/optimized/hello-640.png\n\n";
    echo "If you have questions, ask support@imageoptim.com\n";
}

if (count($_SERVER['argv']) < 4) { // the arg 0 is the command name
    usage();
    exit(1);
}

$argn = 1;
$apiUsername = $_SERVER['argv'][$argn++];
if (!$apiUsername || ctype_digit($apiUsername) || file_exists($apiUsername)) {
    echo "The first argument (". escapeshellarg($apiUsername) . ") must be an ImageOptim API username.\n";
    echo "Get your username from https://imageoptim.com/api/register\n";
    exit(1);
}

$width = null;
if (count($_SERVER['argv']) > 4 && ctype_digit($_SERVER['argv'][$argn])) {
    $width = $_SERVER['argv'][$argn++];
}

$sourceDir = $_SERVER['argv'][$argn++];
if (!is_dir($sourceDir)) {
    echo "ERROR: ", $sourceDir, " does not exist or is not a directory.\n\n";
    usage();
    exit(1);
}

$destDir = $_SERVER['argv'][$argn++];
if (!is_dir($destDir))  {
    if (is_dir(dirname($destDir))) {
        if (!mkdir($destDir)) {
            echo "ERROR: can't create ", $destDir, ". Please create this directory first.\n";
            exit(1);
        }
    } else {
        echo "ERROR: ", $destDir, " does not exist or is not a directory.\n\n";
        usage();
        exit(1);
    }
}

// Clears symlinks from paths, makes them absolute and comparable
$sourceDir = realpath($sourceDir);
$destDir = realpath($destDir);

try {
    $api = new ImageOptim\API($apiUsername);

    // This is a fancy way of getting a list of all files in a directory
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
        RecursiveIteratorIterator::CATCH_GET_CHILD);

    $nonImage = [];
    $skipped = 0;
    $done = 0;

    foreach ($items as $item) {
        if ($item->isDir()) continue;
        $filename = $item->getFilename();
        if (!preg_match('/\.(png|jpe?g|gif|svgz?|bmp|tiff?)/i', $filename)) {
            $nonImage[] = $filename;
            continue;
        }

        $sourcePath = $item->getPathname();
        $destRelPath = substr($sourcePath, strlen($sourceDir));
        $destPath = $destDir . $destRelPath;

        // Append .min extension if source and destination are the same
        if ($destPath === $sourcePath && false === strpos($destRelPath, '.min.')) {
            $destRelPath = preg_replace('/\.[^.]+$/', '.min$0', $destRelPath);
            $destPath = $destDir . $destRelPath;
        }

        echo substr($destRelPath,1),"... ";

        if (file_exists($destPath) && filemtime($destPath) > filemtime($sourcePath)) {
            echo " already exists (skipped)\n";
            $skipped++;
            continue;
        }

        // The process preserves directory structure, so it needs to create dirs
        $destSubdir = dirname($destPath);
        if (!is_dir($destSubdir)) {
            if (!mkdir($destSubdir, 0777, true)) {
                echo "error: unable to create", $destSubdir,"\n";
                continue;
            }
        }

        $apiRequest = $api->imageFromPath($sourcePath);
        if ($width) {
            // You could add more options here
            $apiRequest->resize($width);
        }
        $data = $apiRequest->getBytes();
        if (!file_put_contents($destPath, $data)) {
            echo "ERROR: unable to save file $destPath\n";
            break;
        }

        $inSize = filesize($sourcePath);
        $outSize = strlen($data);
        echo "ok (", ($inSize > $outSize ? "$inSize -> $outSize bytes" : "already optimized"), ")\n";
        $done++;
    }

    if (count($nonImage)) {
        echo "Skipped ", count($nonImage), " non-image file(s) ", implode(', ', array_slice($nonImage, 0, 50)), "\n";
        $nonImage = [];
    }

    if ($skipped) {
        echo "\nSkipped $skipped alredy-existing file(s) in $destDir";
    }
    echo "\nImageOptim API processed $done file(s)\n";

} catch(\ImageOptim\AccessDeniedException $e) {
    echo "ERROR\n\n";
    echo "Please got to https://imageoptim.com/api/register\n";
    echo "get your API username, and replace '$apiUsername' with\n";
    echo "your new registered API username.\n\n";
    echo $e;
    exit(1);
} catch(\Exception $e) {
    echo "ERROR\n\n";
    echo $e;
    exit(1);
}
