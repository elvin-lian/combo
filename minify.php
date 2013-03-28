<?php
// 仅合并压缩 js, css

// Assets 服务器根目录 -- 修改为你合适的目录
if (!isset($ASSETS_ROOT_PATH)) $ASSETS_ROOT_PATH = dirname(dirname(__FILE__)) . '/';

// 合并压缩后的文件存储位置，如果为空，不保存
if (!isset($COMPILE_SAVE_PATH)) $COMPILE_SAVE_PATH = '';

/* 是否压缩 */
$MINIFY = TRUE;

require 'jsmin.php';
require 'cssmin.php';

//得到扩展名
function get_extend($file_name)
{
    $extend = explode(".", $file_name);
    $va = count($extend) - 1;
    return $extend[$va];
}

function is_cached($etag)
{
    return ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)) ? true : false;
}

$type = '';
$last_modified_time = 0;
$files = array();
$local_files = array(); // 本地存在的文件
$header = array(
    'js' => 'Content-Type: application/x-javascript',
    'css' => 'Content-Type: text/css'
);

// 处理请求中附带的文件列表，得到原始数据
$split_a = explode("??", $_SERVER['REQUEST_URI']);
if (preg_match('/,/', $split_a[1])) { //多文件
    $_tmp = explode(',', $split_a[1]);
    foreach ($_tmp as $v) {
        $files[] = $v;
    }
} else { //单文件
    $files[] = $split_a[1];
}

// 得到需要读取的文件列表
foreach ($files as $k) {
    //将开头的/和?去掉
    $k = preg_replace(array('/^\//', '/\?.+$/'), array('', ''), $k);

    //最后可能是一个逗号
    if (!preg_match('/(\.js|\.css)$/', $k)) continue;

    while (preg_match('/[^\/]+\/\.\.\//', $k)) {
        $k = preg_replace(array('/[^\/]+\/\.\.\//'), array(''), $k, 1);
    }

    if (empty($type)) $type = get_extend($k);
    if (!preg_match('/js|css/', $type)) continue;

    if (file_exists($ASSETS_ROOT_PATH . $k)) {
        $file_mtime = filemtime($ASSETS_ROOT_PATH . $k);
        if ($file_mtime && ($file_mtime > $last_modified_time)) {
            $last_modified_time = $file_mtime;
        }
        $local_files[] = $ASSETS_ROOT_PATH . $k;
    }
}

if (!isset($header[$type])) {
    header('status: 404');
    echo '404 not found';
    exit;
}

// 生成 etag
$etag = md5($last_modified_time . implode('__', $local_files) . $last_modified_time);

// output
header($header[$type]);
header("Expires: " . date("D, j M Y H:i:s", strtotime("now + 10 years")) . " GMT");
header("Cache-Control: max-age=315360000");
if ($last_modified_time > 0) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified_time) . ' GMT');
}
if (is_cached($etag)) { // 如果浏览器有缓存, 返回304
    header('Etag:' . $etag, true, 304);
    exit;
} else {
    header('Etag:' . $etag);
}

$compiled_file = '';
if (!empty($COMPILE_SAVE_PATH)) {
    $compiled_file = $COMPILE_SAVE_PATH . $etag . '.' . $type;
}

// 如果压缩后的缓存文件存在，直接读取
if (!empty($compiled_file) && file_exists($compiled_file)) {
    $result = file_get_contents($compiled_file);

} else {
    // 压缩 js, css
    $css_compressor = new CSSmin();
    $res_files = array();
    foreach ($local_files as $k) {
        $in_str = file_get_contents($k);
        if ($MINIFY == true && $type == 'js') {
            $res_files[] = JSMin::minify($in_str);
        } else if ($MINIFY == true && $type == 'css') {
            $res_files[] = $css_compressor->run($in_str, 2000);
        } else {
            $res_files[] = $in_str;
        }
    }
    $result = join("", $res_files);
    file_put_contents($compiled_file, $result);
}
echo $result;
exit;