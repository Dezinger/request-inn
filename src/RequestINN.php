<?php

namespace Dezinger\RequestINN;

/**
 * Класс получения ИНН
 */
class RequestINN 
{
    const SERVICE_NALOG = 0;
    const SERVICE_GOSUSLUGI = 1;
    
    const BASE_URL = 0;
    const CAPTCHA_URI = 1;
    const CAPTCHA_SRC = 2; 
    const CHECK_URI = 3;
    
    //Хеш сообщения когда нужно его отличить от ошибки или об ненайденом ИНН
    //такой ответ характерен для сервиса госуслуг (SERVICE_GOSUSLUGI)
    const NOTFOUND_HASH = '628f1a8279fc6edf7acee3ffb00427ce';
    
    protected $current_service = self::SERVICE_NALOG;

    protected $current_config;

    protected $configs = array(
        self::SERVICE_NALOG => array(
            self::BASE_URL => 'https://service.nalog.ru/',
            self::CAPTCHA_URI => 'static/captcha.html?',
            self::CAPTCHA_SRC => 'static/captcha.html?a=%s',
            self::CHECK_URI => 'inn-proc.do'
        ),
        self::SERVICE_GOSUSLUGI => array(
            self::BASE_URL => 'https://www.gosuslugi.ru/pgu/',
            self::CAPTCHA_URI => 'fns/findInn/order?',
            self::CAPTCHA_SRC => 'captcha/get?id=%s',
            self::CHECK_URI => 'fns/findInn/order'
        ),
    );


    protected $convert_fields = array(
        self::SERVICE_GOSUSLUGI => array(
            'fam' => 'sF',
            'nam' => 'sI',
            'otch' => 'sO',
            'bdate' => 'sDR',
            'bplace' => 'sMR',
            'doctype' => 'sDoc',
            'docno' => 'sSNDoc',
            'docdt' => 'sDDoc',
            'captchaToken' => 'captchaId',
            'captcha' => 'captchaAnswer'
        )
    );


    const ERROR_MSG = 'Извините, сервис временно не доступен. 
                       Пожалуйста, повторите запрос позднее.';
    
    /**
     * Сервис получения ИНН довольно 
     * медленный поэтому увеличиваем 
     * время соединения
     */
    const CONNECT_TIMEOUT = 300;
    
    /**
     * Значение ИНН
     * 
     * @var string
     */
    protected $inn;

    /**
     * Список ошибок
     * 
     * @var array
     */
    protected $errors;
    
    
    /**
     * Код токена капчи если 
     * текущий запрос проверки ИНН 
     * его возвращает
     * 
     * @var boolean 
     */
    protected $next_token = false;



    
    /**
     * Использовать прокси сервера 
     * для подключения к сайтам проверки ИНН
     * Используется только для получения капчи
     * 
     * @var boolean 
     */
    protected $used_proxy = false;
    
    
    /**
     * Список прокси серверов
     * 
     * @var array 
     */
    protected $proxy_servers = array();



    public function __construct($service = self::SERVICE_NALOG) 
    {
        if (isset($this->configs[$service])) {
            $this->current_config = $this->configs[$service];
            $this->current_service = $service;
        } else {
            $this->current_config = $this->configs[$this->current_service];
        }
    }
    

    
    protected function getConfig($type)
    {
        return $this->current_service[$type];
    }

    
    
    protected function getCaptchaUri()
    {
        return $this->current_config[self::BASE_URL] . 
               $this->current_config[self::CAPTCHA_URI] . 
               time();
    }

    
    protected function getCaptchaSrc($token)
    {
        return sprintf(
                $this->current_config[self::BASE_URL] . 
                $this->current_config[self::CAPTCHA_SRC], 
                $token);
    }

    
    protected function getCheckUri()
    {
        return $this->current_config[self::BASE_URL] . 
               $this->current_config[self::CHECK_URI];
    }

    

    /**
     * Преобразуем данные к нужному формату для текущего сервиса
     * 
     * @param type $data
     * @return type
     */
    protected function convertFields($data)
    {
        $_data = $data;
        if (isset($this->convert_fields[$this->current_service])) {
            $_data = array();
            foreach ($this->convert_fields[$this->current_service] as $key => $value) {
                if (isset($data[$key])) {
                    $_data[$value] = $data[$key];
                }
            }
        }
        
        return $_data;
    }


    
    /**
     * Вернуть прокси выбранный случайным образом
     * 
     * @return string url
     */
    protected function getProxy()
    {
        $cnt = count($this->proxy_servers)-1;
        $proxy = $this->proxy_servers[rand(0, $cnt)];
        return $proxy;
    }

   
    /**
     * Указать список прокси серверов 
     * в формате array('xxx.xxx.xxx.xxx:8085','xxx.xxx.xxx.xxx:8085',...)
     * 
     * @param array $servers
     * @return $this
     */
    public function setProxyServers($servers)
    {
        $this->proxy_servers = $servers;
        return $this;
    }


    /**
     * Есть возможность использовать прокси
     * 
     * @return boolean
     */
    protected function isCanProxy()
    {
        return $this->used_proxy && 
               !empty($this->proxy_servers);
    }

    

    /**
     * Запрос данных каптчи
     * 
     * @return array
     */
    public function getCaptcha()
    {
        $result = array(
            'token' => '',
            'image' => ''
        );
        
        $token = $this->next_token;
        
        if (!$token) {

            if ($this->isCanProxy()) {
                $aContext = array(
                    'http' => array(
                        'proxy' => 'tcp://' . $this->getProxy(),
                        'request_fulluri' => true,
                        //'header' => "Proxy-Authorization: Basic $auth",
                    ),
                );

                $cxContext = stream_context_create($aContext);
                $content = file_get_contents($this->getCaptchaUri(), false, $cxContext);
            } else {
                $content = file_get_contents($this->getCaptchaUri());
            }
            
            switch ($this->current_service) {
                case self::SERVICE_GOSUSLUGI:
                    $content = (array)json_decode($content);
                    if (isset($content['captchaId']) && 
                        preg_match('/([0-9]+)/', 
                                $content['captchaId'], $matches) ) {

                        $token = $matches[1];
                    }
                    break;

                case self::SERVICE_NALOG:
                default:
                    if (preg_match('/([0-9A-Z]+)/',
                    $content, $matches)) {
                        $token = $matches[1];
                    }
            }
        }
        
        if ($token) {
            $result['token'] = $token;
            $result['image'] = $this->getCaptchaSrc($token);
        }    

        return $result;
    }
    
    
    
    /**
     * Получить значение ИНН 
     * после успешного запроса isValid
     * 
     * Если пусто значит ИНН 
     * по указанным данным нет
     * 
     * @return type
     */
    public function getINN()
    {
        return $this->inn;
    }
    
    
    /**
     * Отправить запрос
     * 
     * @param type $data
     */
    public function isValid($data)
    {
        $is_valid = true;
        $this->errors = array();
        
        $config = array(
            //'adapter' => 'HTTP_Request2_Adapter_Curl',
            'connect_timeout' => self::CONNECT_TIMEOUT, 
            'timeout' => self::CONNECT_TIMEOUT,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false,
        );

        /*
        if ($this->isCanProxy()) {
            $config['proxy'] = 'socks5://' . $this->getProxy();
        }
        */
        
        $request = new \HTTP_Request2($this->getCheckUri());
        $request->setConfig($config);

        $request->setMethod(\HTTP_Request2::METHOD_POST)
                ->addPostParameter($this->convertFields($data));
        $response = $request->send();
        $is_valid = ($response->getStatus() == 200);
        
        switch ($this->current_service) {
        
            case self::SERVICE_GOSUSLUGI:
                if ($is_valid) {
                    $result = (array)json_decode($response->getBody());
                    
                    $this->inn = isset($result['INN'])?$result['INN']:false;
                    $this->next_token = isset($result['captchaId'])?intval($result['captchaId']):false;
                    
                    if (isset($result['errorMessage']) && 
                        md5($result['errorMessage']) != self::NOTFOUND_HASH) {
                        $is_valid = false;
                        $this->errors[] = $result['errorMessage'];
                    }
                } else {
                    $this->errors[] = self::ERROR_MSG;
                }
                
                break;
                
                
            case self::SERVICE_NALOG:
            default:
                if ($is_valid) {
                    
                    $result = (array)json_decode($response->getBody());
                    $this->inn = isset($result['inn'])?$result['inn']:false;
                    
                    if (isset($result['code']) && $result['code'] > 3) {
                        $is_valid = false;
                        $this->errors[] = self::ERROR_MSG;
                    }
                    
                } else {
                    $result = json_decode($response->getBody());
                    if ($result) {
                        foreach ($result->ERRORS as $key => $error) {
                            $this->errors[$key] = current($error);
                        }   
                    }
                }
        }

        return $is_valid;
    }
    
    
    
    /**
     * Использовать прокси
     * 
     * @param type $used
     */
    public function usedProxy($used = true)
    {
        $this->used_proxy = $used;
        return $this;
    }
    
    
    /**
     * Если isValid вернул FALSE 
     * метод вернет детали ошибок запроса
     * 
     * @return type
     */
    public function getErrors()
    {
        return $this->errors;
    }
}