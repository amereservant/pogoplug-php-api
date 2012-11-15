<?php
// From http://snipplr.com/view.php?codeview&id=4633
function file_size($size)
{
    $filesizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
    return $size ? round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0 Bytes';
}

require 'pogoplug-php-api.class.php';

$pp = new pogoplugAPI('', '');
$devices = $pp->listDevices();
echo '<pre>';
//var_dump($pp->searchFiles('', '',''));

$apiurl = 'http://192.168.0.199/svc/xpl/VEibSg/files';
$vars = array();
$vars['valtoken']   = $_SESSION['valtoken'];
$vars['serviceid']  = '';
$vars['deviceid']   = '';
$vars['fileid']     = '';
$vars['flags']      = 'stream';

$vars['filename']    = '';
var_dump($pp->getFile($vars['deviceid'], $vars['serviceid'], $vars['fileid']));
/*
$fh = fopen($apiurl .'/'. $vars['valtoken'] .'/'. $vars['deviceid'] .'/'. $vars['serviceid'] .
    '/'. $vars['fileid'] .'/'. $vars['flags'] .'/'.$vars['filename'], 'rb');

$fo = fopen($vars['filename'], 'w+');

while( !feof($fh) ) {
    $buffer = fgets($fh, 4096);
    fwrite($fo, $buffer);
}
fclose($fo);
fclose($fh);

*/    
//var_dump($pp->listServices());
$files = $pp->listFiles($vars['deviceid'], $vars['serviceid']);
//$pp->removeFile($vars['deviceid'], $vars['serviceid'], '');
echo '</pre>';
//echo file_get_contents(
?>
<!DOCTYPE html>
<head>
<meta charset="utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<title>Pogoplug API PHP - Test</title>
<meta name="description" content="" />
<meta name="author" content="Amereservant" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
</head>
<body>
    <div id="header-container">
        <header class="wrapper">
            <h1 id="title">Pogoplug API PHP - Test</h1>
        </header>
    </div>
    <div id="main" class="wrapper">
        <article>
            <header>
                <h2>Devices</h2>
            </header>
            <?php 
                $services = '';
            ?>
            <table>
            <?php
                $trf = '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>';
                foreach($devices as $device) {
                    
                    for($i=0;$i<count($device->services);$i++)
                    {
                        $serv      = $device->services[$i];
                        $space     = explode('/', $serv->space);
                        $space     = file_size($space[0]) .' / '. file_size($space[1]);
                        
                        $services .= sprintf($trf, $device->name, $serv->serviceid, $serv->name, $space);
                    }
                    printf($trf, $device->deviceid, $device->name, $device->version, '');
                }
            ?>
                    
            </table>
            <h3>Device Services</h3>
            <table>
            <?php echo $services; ?>
            </table>
            <h3>Files</h3>
            <table>
            <?php foreach($files as $file) {
                printf($trf, $file->name, $file->fileid, file_size($file->size), $file->type);
            } ?>
            </table>
            
        </article>
    </div>
    <div id="footer-container">
        <footer class="wrapper">
            <h3></h3>
        </footer>
    </div>
</body>
</html>
