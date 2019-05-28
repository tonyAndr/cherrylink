<?php
/**
 * Created by PhpStorm.
 * User: Tony
 * Date: 04.11.2018
 * Time: 16:33
 */

$options = get_option('linkate-posts');

$str = http_build_query($options);
header("Content-Disposition: attachment; filename=\"cherrylink_options.txt\"");
header("Content-Type: application/force-download");
header("Content-Length: " . mb_strlen($str));
header("Connection: close");

echo $str;
exit();