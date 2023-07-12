<?php
function writeLog($msg)
{
    file_put_contents('php://stdout', 'ROUTER = ' . $msg . "\n");
}

if (str_starts_with($_SERVER['REQUEST_URI'], '/js/')) {
    $filePath = $_SERVER['DOCUMENT_ROOT'].'/'. $_SERVER['REQUEST_URI'];
    if (!file_exists($filePath)) {
        writeLog('Error: File not found: ' . $_SERVER['REQUEST_URI']);
        http_response_code(404);
        return false;
    }

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Content-type: text/javascript');
    exit(file_get_contents($filePath));
}
else {
    return false;
}
