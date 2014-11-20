<?php

class SitestackerConnect {

    private $domain;
    private $email;
    private $password;
    private $cookieFilePath;
    private $loginUrl;
    private $csrfUrl;
    private $token;
    private $ch;

    public function __construct($domain,$email,$password)
    {
        
        $this->domain = $domain;
        $this->email = $email;
        $this->password = $password;
        $this->cookieFilePath = dirname(__FILE__).DIRECTORY_SEPARATOR.'cookies.txt';
        $this->loginUrl = '/p/Users/Users/login.json';
        $this->csrfUrl = '/p/Developer/Developer/getCsrfToken.json';

        // begin script
        $this->ch = curl_init();

        // extra headers
        $headers = array();
        $headers[] = "Accept: */*";
        $headers[] = "Connection: Keep-Alive";
        $headers[] = "X-Requested-With: XMLHttpRequest";

        // basic curl options for all requests
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFilePath);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieFilePath);

    }

    private function getToken()
    {
        if(empty($this->token)) {
            $r = $this->getRequest($this->csrfUrl);
            $this->token = $r['response']['token'];
        }
    }

    private function login()
    {
        $r = $this->postRrequest($this->loginUrl,array('data'=>array('User'=>array(
            'username' => $this->email,
            'password' => $this->password,
        ))));

        if (!$r['response']['success']) {
            trigger_error('Login Failed: '.implode(';',$response['errors']),E_USER_ERROR);
        }
        
    }
    
    private function flatten($array=array(),$longKey='')
    {
        $flat = array();
        foreach($array as $key => $val) {
            $newKey = empty($longKey)?$key:$longKey.'['.$key.']';
            if(is_array($val)) {
                $flat = array_merge($flat,$this->flatten($val,$newKey));
            } else {
                $flat[$newKey] = $val;
            }
        }
        return $flat;
    }
    
    private function getRequest($url, $params=array())
    {
        if (!empty($params['filter'])) {
            $params['filter'] = json_encode($params['filter']);
        }
        if (!empty($params['sort'])) {
            $params['sort'] = json_encode($params['sort']);
        }
        curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        curl_setopt($this->ch, CURLOPT_URL, $this->domain.$url.(empty($params)?'':'?'.http_build_query($params)));
        $content = curl_exec($this->ch);
        $status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $response = json_decode($content, true);
        return compact('status', 'response');
    }
    
    private function postRrequest($url, $params=array())
    {
        $this->getToken();
        $params['data']['_Token']['key'] = $this->token;
        $params = $this->flatten($params);

        $post = http_build_query($params);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_URL, $this->domain.$url);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
        $content = curl_exec($this->ch);
        curl_setopt($this->ch, CURLOPT_POST, 0);
        $status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $response = json_decode($content, true);
        return compact('status', 'response');
    }

    public function get($url, $params=array())
    {
        $response = $this->getRequest($url, $params);
        if($response['status']=='403') {
            $this->login();
            $response = $this->getRequest($url, $params);
        }
        return $response;
    }

    public function post($url, $params=array())
    {
        $response = $this->postRrequest($url, $params);
        if($response['status']=='403') {
            $this->login();
            $response = $this->postRrequest($url, $params);
        }
        return $response;
    }

}