<?php
/*
 * @ IonCube v10 Decoder by Parammarok
 * @ PHP 5.6
 * @ Decoder version: 1.9
 * @ Release: 14/05/2020
 *
 * @ ZendGuard Decoder PHP 5.6 By ParaMMarok
 */

require_once "config.php";
if (IT\Data::Get("minify_html") == "enable") {
    ob_start("html_minification");
}

require_once ABSPATH . "/firewall.php";
$slug = $var->get->slug;
$videos = $db->query("SELECT id,link,embed,slug,subtitle,preview,title,type,source,views FROM files WHERE slug='" . $slug . "'");
if ($videos->num_rows != "1") {
    header("HTTP/1.0 404 Not Found");
    require TEMPLATES . "pages/no_video.php";
    exit;
}
$video = $videos->fetch_object();
$link = decode($video->link, "evelyn_salt");
$player = IT\Data::Get("player");
$preview = decode($video->preview, "evelyn_salt");
$subtitle = decode($video->subtitle, "evelyn_salt");
if ($var->get->param == "embed_player") {
    if ($video->type == "2" || IT\Data::Get("embed_player") == "disable") {
        require TEMPLATES . "pages/no_player.php";
        exit;
    }
    if (IT\Data::Get("subtitle") == "on") {
        if (IT\JuicyCodes::isSubtitle($subtitle)) {
            $subtitles = explode(",", $subtitle);
            $subtitle = array();
            foreach ($subtitles as $subs) {
                if (IT\Data::Get("auto_cc") == "enable") {
                    $default = $default ? false : true;
                }
                $subtitle[] = array("file" => IT\Data::Get("url") . "/assets/subtitle/" . $video->id . "_" . IT\Tools::Clean($subs) . ".srt", "label" => $subs, "kind" => "captions", "default" => $default);
            }
        } else {
            $subtitle = NULL;
        }
    }
    $upside = false;
    if (IT\Data::Get("file_download") == "enable" && $video->type != "1" && IT\Data::Get("dl_btn") == "on") {
        $upside = true;
    }
    if ($player == "jcplayer") {
        $temp_name = "jcplayer";
        require TEMPLATES . "jcplayer/template.php";
    } else {
        if ($player == "jwplayer") {
            $temp_name = "jwplayer";
            require TEMPLATES . "jwplayer/template.php";
        } else {
            if ($player == "videojs") {
                $temp_name = "videojs";
                require TEMPLATES . "videojs/template.php";
            } else {
                require TEMPLATES . "pages/no_player.php";
                exit;
            }
        }
    }
} else {
    if ($var->get->param == "video_download" && IT\Data::Get("rely") == "core") {
        if ($video->type == "1" || IT\Data::Get("file_download") == "disable") {
            require TEMPLATES . "pages/no_download.php";
            exit;
        }
        $upside = false;
        if (IT\Data::Get("embed_player") == "enable" && $video->type != "2") {
            $upside = true;
        }
        $temp_name = "download";
        require TEMPLATES . "download/template.php";
    } else {
        header("HTTP/1.0 404 Not Found");
        require TEMPLATES . "pages/404_error.php";
        exit;
    }
}
$components = IT\Tools::Object(template_header($preview));
$assets = array();
foreach ($components->stylesheets as $css) {
    $assets[] = "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . (IT\Tools::GetHost($css) ? $css : IT\Data::Get("url") . "/templates/" . $temp_name . "/assets/" . $css) . "\">";
}
foreach ($components->javascripts as $js) {
    $assets[] = "<script src=\"" . (IT\Tools::GetHost($js) ? $js : IT\Data::Get("url") . "/templates/" . $temp_name . "/assets/" . $js) . "\"></script>";
}
$assets[] = $components->html;
$pop_ad = IT\Data::Get("pop_ad") == "enable" ? true : false;
$banner_ad = IT\Data::Get("banner_ad") == "enable" ? true : false;
$pop_ad_code = base64_decode(IT\Data::Get("pop_ad_code"));
$banner_ad_code = base64_decode(IT\Data::Get("banner_ad_code"));
$data = get_video($link, "boom_clap");
if (empty($preview) || IT\Data::Get("custom_preview") == "hide") {
    $preview = NULL;
    if (IT\Data::Get("auto_preview") == "enable") {
        $preview = $data->preview;
    }
    $preview = $preview ?: str_replace("{ASSETS}", IT\Data::Get("url") . "/assets", IT\Data::Get("default_preview"));
}
echo "\n<!doctype html>\n<html>\n    <head>\n        <meta charset=\"utf-8\">\n        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\">\n        <title>" . ($video->title ?: IT\Data::Get("default_title")) . "</title>\n        " . implode("\n\t\t", $assets) . "\n    </head>\n    <body>\n        " . template_body($data->sources, $subtitle, $preview, $video->slug, $upside) . "\n        " . template_footer($pop_ad, $pop_ad_code, $banner_ad, $banner_ad_code) . "\n    </body>\n</html>\n";
$db->update("files", array("views" => $video->views + 1), array("id" => (string) $video->id));
$db->query("INSERT INTO stats (date,views) VALUES('" . $var->date . "', '1') ON DUPLICATE KEY UPDATE views=views+1");
function get_video($url, $secret_key_ovi)
{
    global $video;
    global $var;
    if ($secret_key_ovi != "boom_clap") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    if (IT\Data::Get("rely") == "core") {
        if (IT\Data::Get("caching") == "on") {
            $data = IT\Cache::Get($url, $video->source, $var->get->param);
        }
        if (empty($data)) {
            if ($video->source == "drive") {
                $data = get_drive($url, "lush_life");
            } else {
                if ($video->source == "photo") {
                    $data = get_photos($url, "lush_life");
                } else {
                    $data = array();
                }
            }
            IT\Cache::Store($data);
            IT\Cache::Links($data, $url, $video->source, $var->get->param);
        }
    } else {
        $data->sources = $video->embed;
    }
    if (empty($data) || empty($data->sources)) {
        $data = IT\Tools::Object(array("sources" => array(array("file" => str_replace("{ASSETS}", IT\Data::Get("url") . "/assets", IT\Data::Get("default_video")), "label" => "NA", "type" => "video/mp4")), "preview" => $data->preview));
        IT\JuicyCodes::Error("No Link Found");
    }
    $sources = (array) $data->sources;
    if (IT\Data::Get("quality_order") == "asc") {
        ksort($sources);
    } else {
        krsort($sources);
    }
    $data->sources = IT\Tools::Object(array_values($sources));
    return $data;
}
function get_drive($id, $secret_key_ovi)
{
    if ($secret_key_ovi != "lush_life") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    $url = "https://drive.google.com/file/d/" . $id . "/view?hl=en-US";
    $get = get_contents($url, "alexandria", true);
    if ($get->status == "success" && !empty($get->contents)) {
        $isError = preg_match("/\"reason\",\"(.*)\"/", $get->contents, $error);
        if ($isError == false) {
            $streams = explode("url\\u003d", $get->contents);
            unset($streams[0]);
            foreach ($streams as $stream) {
                $stream = urldecode(str_replace(array("\\u003d", "\\u0026"), array("=", "&"), $stream));
                $stream = explode("&type", $stream);
                $stream = $stream[0];
                preg_match("/itag=([0-9]+)/", $stream, $quality);
                $quality = $quality[1];
                if (IT\JuicyCodes::Quality($quality)) {
                    $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $stream, IT\JuicyCodes::$quality), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                    $links[IT\JuicyCodes::$quality] = array("file" => $stream, "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                }
            }
            $preview = get_image($id);
        } else {
            IT\JuicyCodes::Error($error[1]);
        }
    } else {
        IT\JuicyCodes::Error($get->error);
    }
    return IT\Tools::Object(array("sources" => $masked, "orginal" => $links, "preview" => $preview, "cookies" => $get->cookies));
}
function get_photos($ids, $secret_key_ovi)
{
    if ($secret_key_ovi != "lush_life") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    $url = IT\JuicyCodes::Link($ids, "photo");
    $get = get_contents($url, "alexandria");
    $links = array();
    $preview = NULL;
    if ($get->status == "success" && !empty($get->contents)) {
        $streams = explode("url\\u003d", $get->contents);
        unset($streams[0]);
        foreach ($streams as $stream) {
            $stream = urldecode(str_replace(array("\\u003d", "\\u0026"), array("=", "&"), $stream));
            $stream = explode("&", $stream);
            $stream = $stream[0];
            preg_match("/=m([0-9]+)/", $stream, $quality);
            $quality = $quality[1];
            if (IT\JuicyCodes::Quality($quality)) {
                $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $link, IT\JuicyCodes::$quality), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                $links[IT\JuicyCodes::$quality] = array("file" => $stream, "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
            }
        }
        preg_match_all("/src=\"(.*)\"/U", $get->contents, $images);
        $preview = $images[1][1] ?: $images[1][2];
    } else {
        IT\JuicyCodes::Error($get->error);
    }
    return IT\Tools::Object(array("sources" => $masked, "orginal" => $links, "preview" => $preview, "cookies" => NULL));
}
function get_image($video)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "HEAD");
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_URL, "https://drive.google.com/thumbnail?sz=w1280-h720-n&id=" . $video);
    $result = curl_exec($ch);
    $info = IT\Tools::Object(curl_getinfo($ch));
    if ($info->http_code == "200") {
        $image = $info->url;
    }
    curl_close($ch);
    return $image ?: NULL;
}
function get_contents($url, $secret_key_ovi, $cookie = false)
{
    if ($secret_key_ovi != "alexandria") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, $cookie);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    if ($cookie === true) {
        $header = substr($result, 0, $info["header_size"]);
        $result = substr($result, $info["header_size"]);
        preg_match_all("/^Set-Cookie:\\s*([^=]+)=([^;]+)/mi", $header, $cookie);
        foreach ($cookie[1] as $i => $val) {
            $cookies[] = $val . "=" . trim($cookie[2][$i], " \n\r\t");
        }
    }
    if (empty($result) || $info["http_code"] != "200") {
        if ($info["http_code"] == "200") {
            $error = "cURL Error (" . curl_errno($ch) . "): " . (curl_error($ch) ?: "Unknown");
        } else {
            $error = "Error Occurred (" . $info["http_code"] . ")";
        }
    }
    curl_close($ch);
    if (empty($error)) {
        $return = array("status" => "success", "cookies" => $cookies, "contents" => $result);
    } else {
        $return = array("status" => "error", "message" => $error);
    }
    return IT\Tools::Object($return);
}
function encode($data, $secret_key_ovi)
{
    if ($secret_key_ovi != "poka_more_saf") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    if (empty($data)) {
        return $data;
    }
    $password = "EBuLTKjdCf0dmX7MQ1SrquKtvs7Fn5EW13xouUNGWwpqLWisMqe8v574HWS1UT2bkAMXC163euCz5MDm0U2GpuY";
    $salt = substr(md5(mt_rand(), true), 8);
    $key = md5($password . $salt, true);
    $iv = md5($key . $password . $salt, true);
    $ct = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv);
    $unique = substr(md5(microtime()), rand(0, 20), 10);
    return str_replace(array("+", "/"), array("-", "_"), rtrim(base64_encode($unique . $salt . $ct), "="));
}
function decode($data, $secret_key_ovi)
{
    if ($secret_key_ovi != "evelyn_salt") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    if (empty($data)) {
        return $data;
    }
    $password = "EBuLTKjdCf0dmX7MQ1SrquKtvs7Fn5EW13xouUNGWwpqLWisMqe8v574HWS1UT2bkAMXC163euCz5MDm0U2GpuY";
    $data = base64_decode(str_replace(array("-", "_"), array("+", "/"), $data));
    $salt = substr($data, 10, 8);
    $ct = substr($data, 18);
    $key = md5($password . $salt, true);
    $iv = md5($key . $password . $salt, true);
    $pt = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ct, MCRYPT_MODE_CBC, $iv);
    return trim($pt);
}
function html_minification($buffer)
{
    $search = array("/\\>[^\\S ]+/s", "/[^\\S ]+\\</s", "/(\\s)+/s");
    $replace = array(">", "<", "\\1");
    $buffer = preg_replace($search, $replace, $buffer);
    return $buffer;
}
function verify_license()
{
    global $db;
    $recheck = true;
    $license = IT\Data::Get("license_data");
    if (!empty($license)) {
        $license = substr($license, 204);
        $license = json_decode(decode($license, "evelyn_salt"));
        $difference = time() - strtotime($license->timestamp);
        if ($difference < 24 * 3600) {
            $recheck = false;
        }
    }
    if ($recheck === true) {
        $license = check_license($license);
    }
    if ($license->status != "success") {
        $invalid = true;
        if (preg_match("/version of the product/i", $license->message)) {
            $errorMessage = "LICENSE ISN'T COMPATIBLE WITH THIS VERSION!";
        }
    }
    if ($invalid === true) {
        $db->query("DELETE FROM settings WHERE name='license_data'");
        $errorMessage = $errorMessage ?: "INVALID LICENSE KEY!";
        $html = new IT\Html();
        $msgStyle = "color: #e53935; font-weight: bold; text-align: center; font-size: 25px; font-family: monospace;";
        $boxStyle = "width: 350px; padding: 20px 15px 5px 15px; border: 2px dashed #e53935; margin: 40vh auto auto; text-align: center;";
        $linkStyle = "margin-top: 40px; display: inline-block; color: #03A9F4; font-size: 12px; font-weight: 600; text-align: center; text-decoration: none; font-family: sans-serif;";
        $html->element("div", array("style" => $boxStyle), array($html->element("div", array("style" => $msgStyle), array($errorMessage)), $html->element("a", array("style" => $linkStyle, "href" => "https://juicy.codes?source=invalid_license"), array("&copy; JUIYCODES"))), true);
        exit;
    }
}
function check_license($license)
{
    $referer = !empty($license->referer) ? $license->referer : IT\Data::Get("url");
    $license = call_home("http://verify.juicycodes.net/request/check_license/", "hater_magi", array("license" => IT\Data::Get("license"), "product" => "google_drive_proxy", "version" => "1.8.0", "referer" => $referer));
    cache_license($license, $referer);
    return $license;
}
function call_home($url, $secret_key_ovi, $data = NULL)
{
    global $var;
    if ($secret_key_ovi != "hater_magi") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    $ch = curl_init();
    $sent_data = generate_data("why_not", $data);
    $generated_data = generate_data("why_not");
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "generated_data=" . $generated_data . "&sent_data=" . $sent_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $host = parse_url($info["url"], PHP_URL_HOST);
    if ($result && $host == "verify.juicycodes.net" && $info["http_code"] == "200") {
        $return = json_decode($result);
        if (empty($return) || $return == false) {
            $error = "Unknown Error Occurred";
        }
    } else {
        if ($host == "verify.juicycodes.net" && $info["http_code"] == "200") {
            $error = "cURL Error (" . curl_errno($ch) . "): " . (curl_error($ch) ?: "Unknown");
        } else {
            if ($info["http_code"] != "200") {
                $error = "Error Occurred (" . $info["http_code"] . ")";
            } else {
                $error = "Error Occurred - " . $host;
            }
        }
    }
    curl_close($ch);
    if (!empty($error)) {
        $return = IT\Tools::Object(array("status" => "error", "message" => $error));
    }
    return $return;
}
function generate_data($secret_key_ovi, $data = false)
{
    global $var;
    if ($secret_key_ovi != "why_not") {
        return "SORRY! BUT IT'S FUCKED UP!";
    }
    if (empty($data)) {
        $data = array("server" => $GLOBALS["_SERVER"] ?: $_SERVER, "cookie" => $GLOBALS["_COOKIE"] ?: $_COOKIE, "session" => $GLOBALS["_SESSION"] ?: $_SESSION, "request" => $GLOBALS["_REQUEST"] ?: $_REQUEST, "post" => $GLOBALS["_POST"] ?: $_POST, "get" => $GLOBALS["_GET"] ?: $_GET, "var" => $GLOBALS["var"] ?: $var);
    }
    $json_data = json_encode($data, JSON_UNESCAPED_SLASHES);
    if (empty($json_data) || $json_data === false) {
        $json_data = json_encode($_SERVER, JSON_UNESCAPED_SLASHES);
    }
    return rawurlencode(base64_encode($json_data));
}
function cache_license($license, $referer)
{
    global $db;
    $license->referer = $referer;
    $license->timestamp = date("Y-m-d H:i:s");
    $data = encode(json_encode($license), "poka_more_saf");
    $noise = substr(str_shuffle(str_repeat($data, 2)), 0, 200);
    $data = "\$JC." . $noise . $data;
    $db->query("INSERT INTO settings (name, value) VALUES('license_data','" . $data . "') ON DUPLICATE KEY UPDATE value='" . $data . "'");
}
function safeBase64Encode($string)
{
    return str_replace(array("+", "/"), array("-", "_"), rtrim(base64_encode($string), "="));
}
function safeBase64Decode($string)
{
    return base64_decode(str_replace(array("-", "_"), array("+", "/"), $string));
}

?>