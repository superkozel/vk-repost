<?php

if (!class_exists('Zend_Http_Client')){
    require_once(dirname(__FILE__).'/Zend/Http/Client.php');
    require_once(dirname(__FILE__).'/Zend/Http/Cookie.php');
    require_once(dirname(__FILE__).'/Zend/Http/CookieJar.php');
    require_once(dirname(__FILE__).'/Zend/Http/Exception.php');
    require_once(dirname(__FILE__).'/Zend/Http/Response.php');
    require_once(dirname(__FILE__).'/Zend/Http/UserAgent.php');
}

class Vkontakte
{
    protected $groupId, $appId, $secretKey, $accessToken, $accessSecret;

    /**
     * @param int $groupId
     * @param int $appId
     * @param string $secretKey
     */
    public function __construct($groupId, $appId, $secretKey, $userId, $userPassword)
    {
        $this->groupId = $groupId;
        $this->appId = $appId;
        $this->secretKey = $secretKey;

        $this->userId = $userId;
        $this->userPassword = $userPassword;
    }

    /**
     *
     * @param string $accessToken
     * @param string $accessSecret
     */
    public function setAccessData($accessToken, $accessSecret)
    {
        $this->accessToken = $accessToken;
        $this->accessSecret = $accessSecret;
    }

    //http-авторизация на сайте
    function login(){
        $data = [
            '_origin' => 'http://vk.com',
            'act' => 'login',
            'captcha_key' => '',
            'captcha_sid' => '',
            'email' => $this->userId,
            'pass' => $this->userPassword,
            'expire' => 0,
            'ip_h' => '82dac14382eb851e12',
            'role' => 'al_frame'];

        $uri = "https://login.vk.com/?act=login";
        $client = $this->getHttpClient($uri);
        $client->resetParameters();
        $client->setCookie('remixlang', 0);
        $client->setCookie('remixflash', '11.4.402');
        $client->setCookie('remixdt', 0);
        $client->setParameterPost($data);
        try {
            $res = $client->request($client::POST);
        } catch (Exception $exc) {
            debug($client->getCookieJar());
            echo $exc->getTraceAsString();
        }
    }

    //авторизация oauth, получение токена
    function auth($callback){
        $callback = 'http://api.vk.com/blank.html';
        $uri = "http://api.vkontakte.ru/oauth/authorize?client_id={$this->appId}&scope=notify,friends,photos,audio,video,docs,notes,pages,wall,groups,ads&redirect_uri={$callback}&response_type=code";
        $client = $this->getHttpClient($uri);
        $res = $client->request();
        $body = $res->getBody();

        //grant access
        preg_match('/location.href = "(.*?)"/i', $body, $matches);
        $grantHref = $matches[1];
        $client = $this->getHttpClient($grantHref);
        $res = $client->request($client::POST);
        $code = str_replace('code=', '', $client->getUri()->getFragment());

        return $code;
    }

    function getSecret($callback, $code){

        $uri = "https://api.vkontakte.ru/oauth/access_token?client_id={$this->appId}&redirect_uri={$callback}&client_secret={$this->secretKey}&code=$code";
        $client = $this->getHttpClient($uri);
        $res = $client->request();

        return json_decode($res->getBody(), true);
    }

    /**
     * @param string $method
     * @param mixed $parameters
     * @return mixed
     */
    public function callMethod($method, $parameters)
    {
        if (!$this->accessToken) return false;
        if (is_array($parameters)) $parameters = http_build_query($parameters);
        $queryString = "/method/$method?$parameters&access_token={$this->accessToken}";
        $querySig = md5($queryString . $this->accessSecret);
        return json_decode(file_get_contents(
            "https://api.vk.com{$queryString}&sig=$querySig"
        ));
    }

    /**
     * @param string $message
     * @param bool $fromGroup
     * @param bool $signed
     * @return mixed
     */
    public function wallPostMsg($message, $fromGroup = true, $signed = false)
    {
        return $this->callMethod('wall.post', array(
            'owner_id' => -1 * $this->groupId,
            'message' => $message,
            'from_group' => $fromGroup ? 1 : 0,
            'signed' => $signed ? 1 : 0,
        ));
    }

    /**
     * @param string $attachment
     * @param null|string $message
     * @param bool $fromGroup
     * @param bool $signed
     * @return mixed
     */
    public function wallPostAttachment($attachment, $message = null, $fromGroup = true, $signed = false)
    {
        return $this->callMethod('wall.post', array(
            'owner_id' => -1 * $this->groupId,
            'attachment' => strval($attachment),
            'message' => $message,
            'from_group' => $fromGroup ? 1 : 0,
            'signed' => $signed ? 1 : 0,
        ));
    }

    /**
     * @param string $file relative file path
     * @return mixed
     */
    public function createPhotoAttachment($file)
    {
        $result = $this->callMethod('photos.getWallUploadServer', array(
            'gid' => $this->groupId
        ));

        $ch = curl_init($result->response->upload_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'photo' => '@' . getcwd() . '/' . $file
        ));
        debug('@' . getcwd() . '/' . $file);

        if (($upload = curl_exec($ch)) === false) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        $upload = json_decode($upload);
        $result = $this->callMethod('photos.saveWallPhoto', array(
            'server' => $upload->server,
            'photo' => $upload->photo,
            'hash' => $upload->hash,
            'gid' => $this->groupId,
        ));

        return $result->response[0]->id;
    }

    public function combineAttachments()
    {
        $result = '';
        if (func_num_args() == 0) return '';
        foreach (func_get_args() as $arg) {
            $result .= strval($arg) . ',';
        }
        return substr($result, 0, strlen($result) - 1);
    }

    protected static $client;

    /**
     *
     * @param type $uri
     * @return Zend_Http_Client
     */
    function getHttpClient($uri){
        return self::_getHttpClient($uri);
    }

    function _getHttpClient($uri){
        if (empty(self::$client)){
            $client = new Zend_Http_Client(null, $config = array(
                'adapter' => 'Zend_Http_Client_Adapter_Curl',
                'curloptions' => array(
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_FOLLOWLOCATION => 0,
                    CURLOPT_HEADER => 1),
            ));
            $jar = new Zend_Http_CookieJar;
            $client->setCookieJar($jar);
            self::$client = $client;
        }
        $client = self::$client;

        $client->resetParameters();
        $client->setUri($uri);

        $host = parse_url($uri)['host'];
        $client->setHeaders([
            'Accept' =>	'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Connection' =>	'keep-alive',
            'Host' => $host,
            'Referer' => 'http://vk.com/login?act=mobile',
            'User-Agent' =>	'Mozilla/5.0 (Windows NT 6.1; rv:16.0) Gecko/20100101 Firefox/16.0 FirePHP/0.7.1',
            'x-insight'	=> 'activate'
        ]);

        return $client;
    }
}