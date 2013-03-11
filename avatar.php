<?php

# Resize avatar image (sampled), keeping aspect ratio
# Jochem Kuijpers - 2012

// config also loads Database.class.php (which we don't need) and ../SSI.php
include 'config.php';

$userid = (int) $_GET['u'];

# clearDirectory()
# ~ Deletes all files in the directory. Used for cachecleaning
function clearDirectory($dir) {
    $files = glob($dir . '*.*');
    array_map('unlink', $files);
}

# getImageData()
# ~ Downloads an image and returns it's data via curl
function getImageData($urlparameter) {
    $ch = curl_init();
    $timeout = 5;

    if (strpos($urlparameter, '?') !== false) {
        list($url,$querystring) = explode('?', $urlparameter, 2);
        $url = str_replace(' ', '%20', $url) . '?' . str_replace(' ', '+', $querystring);
    } else {
        $url = str_replace(' ', '%20', $urlparameter);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);

    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
        $data = -abs(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    }
    curl_close($ch);

    return $data;
}

# getimagesizefromstring()
# ~ PHP >= 5.4.0 defines this function

if (!loadMemberData($userid) || !loadMemberContext($userid)) {
    // if we can't load memberdata, there's nothing to do..
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
    die('');
} else {
    // get username for friendlier directories.
    $dirname = $userid . '-' . strtolower($memberContext[$userid]['username']);

    if (empty($memberContext[$userid]['avatar']['href'])) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        die('');
    }
    $imgdata = getImageData($memberContext[$userid]['avatar']['href']);

    if (!$config['avatar_enable'] || $imgdata < 0) {
        header('Location: ' . $memberContext[$userid]['avatar']['href']);
        die('');
    }



    if ($config['avatar_cache']) {
        if (file_exists('./avatarcache/' . $dirname . '/' . md5($imgdata) . $config['avatar_size'] . '.png')) {
            header('Content-type: image/png');
            readfile('./avatarcache/' . $dirname . '/' . md5($imgdata) . $config['avatar_size'] . '.png');
            die('');
        } elseif (!file_exists('./avatarcache/' . $dirname . '/')) {
            mkdir('./avatarcache/' . $dirname . '/');
        } else {
            clearDirectory('./avatarcache/' . $dirname . '/');
        }
    }

    // allright.. we're resizing huh..?
    $img = imagecreatefromstring($imgdata);


    $width = imagesx($img); 
    $height = imagesy($img);
    if ($width == 0 || $height == 0) {
        $newwidth = $config['avatar_size'];
        $newheight = $config['avatar_size'];
    } elseif ($width > $height) {
        $newwidth = $config['avatar_size'];
        $newheight = ($height/$width) * $config['avatar_size'];
    } else {
        $newwidth = ($width/$height) * $config['avatar_size'];
        $newheight = $config['avatar_size'];
    }
    $newimg = imagecreatefromstring(base64_decode($config['avatar_image']));
    $transparent = imagecolorallocatealpha($newimg, 0, 0, 0, 127);
    imagecolortransparent($newimg, $transparent);
    imagealphablending($newimg, false);
    imagesavealpha($newimg, true);

    imagecopyresampled($newimg, $img, (($width > $height)?0:($newheight-$newwidth)/2), (($width > $height)?($newwidth-$newheight)/2:0), 0, 0, $newwidth, $newheight, $width, $height);

    if ($config['avatar_cache']) {
        imagepng($newimg, './avatarcache/' . $dirname . '/' . md5($imgdata) . $config['avatar_size'] . '.png', 9);
        imagedestroy($img);
        imagedestroy($newimg);
        header('Content-type: image/png');
        readfile('./avatarcache/' . $dirname . '/' . md5($imgdata) . $config['avatar_size'] . '.png');
    } else {
        header('Content-type: image/png');
        imagepng($newimg, null, 9);
    }
}