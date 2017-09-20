<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/9/20
 * Time: 下午2:14
 */

$url = $_GET['url'];
include_once "TCMPUrlBein.php";

$tcmp = new \live\TCMPUrlBein();
$item= $tcmp ->getM3u8Url($url);

?>


<!DOCTYPE html>
<html>
<head>
    <meta charset=utf-8 />
    <title>fz-live</title>
    <link href="./css/video.css" rel="stylesheet">
    <script src="js/video.js"></script>
    <script src="js/videojs-live.js"></script>
</head>
<body>

    <video id="my_video_1" class="video-js vjs-default-skin" controls preload="auto" width="1000" height="500"
           data-setup='{}'>
        <source src="<?php echo $item->m3u8_url;?>" type="application/x-mpegURL">
    </video>
    <input  value="<?php echo $item->url;?>" style="width: 1000px;">


<script>
</script>
</body>
</html>