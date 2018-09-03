<?php

ini_set('display_errors',1);
error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT ^ E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';
use Dezinger\RequestINN\RequestINN;

$requestINN = new RequestINN();//RequestINN::SERVICE_GOSUSLUGI);


/*
$captcha = $requestINN->getCaptcha();

print_r($captcha);
print_r(PHP_EOL);
exit;
*/

$data = array(
    'c' => 'innMy',
    'fam' => '',
    'nam' => '',
    'otch' => '', 
    'bdate' => '', 
    'bplace' => '',
    'doctype' => 21,
    'docno' => '', 
    'docdt' => '', 
);

if ($requestINN->isValid($data)) {
    
    $inn = $requestINN->getINN();
    var_dump($inn);
    print_r(PHP_EOL);
    
} else {
    $errros = $requestINN->getErrors();
    print_r($errros);
    print_r(PHP_EOL);
}
