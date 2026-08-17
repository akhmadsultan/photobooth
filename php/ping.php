<?php
/** ping.php — Health check endpoint */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'success'   => true,
    'message'   => 'pong',
    'server'    => 'PhotoBooth PHP Backend v2.0',
    'time'      => time(),
    'php'       => PHP_VERSION,
    'datetime'  => date('Y-m-d H:i:s')
]);
