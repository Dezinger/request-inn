# Запрос ИНН

Оригинал формы запроса
https://service.nalog.ru/inn-my.do


## Подключение composer


```
$ composer require dezinger/request-inn
```


## Примеры использования

```php

use Dezinger\RequestINN\RequestINN;

$requestINN = new RequestINN();

$captcha = $requestINN->getCaptcha();

```


```php

use Dezinger\RequestINN\RequestINN;

$data = [
    'c' => 'innMy',
    'fam' => '',
    'nam' => '',
    'otch' => '', 
    'bdate' => '', 
    'bplace' => '',
    'doctype' => 21,
    'docno' => '', 
    'docdt' => '', 
    'captcha' => '', 
    'captchaToken' => '' 
];


$requestINN = new RequestINN();

if ($requestINN->isValid($data)) {
    
    $inn = $requestINN->getINN();
    
} else {
    $errors = $requestINN->getErrors();
}

```