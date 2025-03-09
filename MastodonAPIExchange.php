<?php

/**
 * Mastodon-API-PHP : Simple PHP wrapper based on Twitter-API-PHP by J7mbo
 *
 */
class MastodonAPIExchange
{
    /**
     * @var string
     */
    private $oauth_access_token;

    /**
     * @var array
     */
    private $postfields;

    /**
     * @var string
     */
    private $getfield;

    /**
     * @var mixed
     */
    protected $oauth;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $requestMethod;

    /**
     * Create the API access object. Requires an array of settings::
     * oauth access token, oauth access token secret, consumer key, consumer secret
     * These are all available by creating your own application on a mastodon instance
     * Requires the cURL library
     *
     * @throws \Exception When cURL isn't installed or incorrect settings parameters are provided
     *
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        if (!in_array('curl', get_loaded_extensions()))
        {
            throw new Exception('You need to install cURL, see: http://curl.haxx.se/docs/install.html');
        }

        if (!isset($settings['oauth_access_token']))
        {
            throw new Exception('Make sure you are passing in the correct parameters');
        }

        $this->oauth_access_token = $settings['oauth_access_token'];
    }

    /**
     * Set postfields array, example: array('screen_name' => 'J7mbo')
     *
     * @param array $array Array of parameters to send to API
     *
     * @throws \Exception When you are trying to set both get and post fields
     *
     * @return MastodonAPIExchange Instance of self for method chaining
     */
    public function setPostfields(array $array)
    {
        if (!is_null($this->getGetfield()))
        {
            throw new Exception('You can only choose get OR post fields.');
        }

        if (isset($array['status']) && substr($array['status'], 0, 1) === '@')
        {
            $array['status'] = sprintf("\0%s", $array['status']);
        }

        $this->postfields = $array;

        return $this;
    }

    /**
     * Set getfield string, example: '?screen_name=J7mbo'
     *
     * @param string $string Get key and value pairs as string
     *
     * @throws \Exception
     *
     * @return \MastodonAPIExchange Instance of self for method chaining
     */
    public function setGetfield($string)
    {
        if (!is_null($this->getPostfields()))
        {
            throw new Exception('You can only choose get OR post fields.');
        }

        $getfields = preg_replace('/^\?/', '', explode('&', $string));
        $params = array();

        foreach ($getfields as $field)
        {
            if ($field !== '')
            {
                list($key, $value) = explode('=', $field);
                $params[$key] = $value;
            }
        }

        $this->getfield = '?' . http_build_query($params);

        return $this;
    }

    /**
     * Get getfield string (simple getter)
     *
     * @return string $this->getfields
     */
    public function getGetfield()
    {
        return $this->getfield;
    }

    /**
     * Get postfields array (simple getter)
     *
     * @return array $this->postfields
     */
    public function getPostfields()
    {
        return $this->postfields;
    }

    /**
      * Resets the fields to allow a new query
      * with different method
      */
    public function resetFields() {
        $this->postfields = null;
        $this->getfield = null;
        return $this;
    }

    /**
     * Build the Oauth object using params set in construct and additionals
     * passed to this method. 
     *
     * @param string $url           The API url to use.
     * @param string $requestMethod Either POST or GET
     *
     * @throws \Exception
     *
     * @return \MastodonAPIExchange Instance of self for method chaining
     */
    public function buildOauth($url, $requestMethod)
    {
        if (!in_array(strtolower($requestMethod), array('post', 'get')))
        {
            throw new Exception('Request method must be either POST or GET');
        }

        $oauth_access_token = $this->oauth_access_token;

        $oauth = array(
            'oauth_nonce' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token' => $oauth_access_token,
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        );

        $getfield = $this->getGetfield();

        if (!is_null($getfield))
        {
            $getfields = str_replace('?', '', explode('&', $getfield));

            foreach ($getfields as $g)
            {
                $split = explode('=', $g);

                /** In case a null is passed through **/
                if (isset($split[1]))
                {
                    $oauth[$split[0]] = urldecode($split[1]);
                }
            }
        }

        $postfields = $this->getPostfields();

        if (!is_null($postfields)) {
            foreach ($postfields as $key => $value) {
                $oauth[$key] = $value;
            }
        }

        $this->url = $url;
        $this->requestMethod = $requestMethod;
        $this->oauth = $oauth;

        return $this;
    }

    /**
     * Perform the actual data retrieval from the API
     *
     * @param boolean $return      If true, returns data. This is left in for backward compatibility reasons
     * @param array   $curlOptions Additional Curl options for this request
     *
     * @throws \Exception
     *
     * @return string json If $return param is true, returns json data.
     */
    public function performRequest($return = true, $curlOptions = array())
    {
        if (!is_bool($return))
        {
            throw new Exception('performRequest parameter must be true or false');
        }

        $header =  array($this->buildAuthorizationHeader($this->oauth), 'Expect:');

        $getfield = $this->getGetfield();
        $postfields = $this->getPostfields();

        $options = array(
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_HEADER => false,
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ) + $curlOptions;

        if (!is_null($postfields))
        {
            if (str_contains($this->url, 'media') && isset($postfields['file'])) {
                $boundary = uniqid();
                $delimiter = '-------------' . $boundary;
                $files = array('file' => $postfields['file']);
                unset($postfields['file']);
                $post_data = $this->build_data_files($boundary, $postfields, $files);
                $header[] = "Content-Type: multipart/form-data; boundary=" . $delimiter;
                $header[] = "Content-Length: " . strlen($post_data);
                $options[CURLOPT_HTTPHEADER] = $header;
                $options[CURLOPT_POSTFIELDS] = $post_data;
            } else {
                $query = json_encode($postfields);
                $header[] = 'Content-Type: application/json';
                $options[CURLOPT_HTTPHEADER] = $header;
                $options[CURLOPT_POST] = 1;
                $options[CURLOPT_POSTFIELDS] = $query;
            }
        }
        else
        {
            if ($getfield !== '')
            {
                $options[CURLOPT_URL] .= $getfield;
            }
        }

        $feed = curl_init();
        curl_setopt_array($feed, $options);
        $json = curl_exec($feed);

        if (($error = curl_error($feed)) !== '')
        {
            curl_close($feed);
            throw new \Exception($error);
        }

        curl_close($feed);

        return $json;
    }

    /**
     * Private method to generate authorization header used by cURL
     *
     * @param array $oauth Array of oauth data generated by buildOauth()
     *
     * @return string $return Header used by cURL for request
     */
    private function buildAuthorizationHeader(array $oauth)
    {
        $return = 'Authorization: Bearer ' . $oauth['oauth_token'];
        return $return;
    }

    /**
     * Helper method to perform our request
     *
     * @param string $url
     * @param string $method
     * @param string $data
     * @param array  $curlOptions
     *
     * @throws \Exception
     *
     * @return string The json response from the server
     */
    public function request($url, $method = 'get', $data = null, $curlOptions = array())
    {
        if (strtolower($method) === 'get')
        {
            $this->setGetfield($data);
        }
        else
        {
            $this->setPostfields($data);
        }

        return $this->buildOauth($url, $method)->performRequest(true, $curlOptions);
    }

    public function build_data_files($boundary, $fields, $files){
        $data = '';
        $eol = "\r\n";
    
        $delimiter = '-------------' . $boundary;
    
        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . $eol
                  . 'Content-Disposition: form-data; name="' . $name . "\"".$eol.$eol
                  . $content . $eol;
        }
    
        foreach ($files as $name => $content) {
            $data .= "--" . $delimiter . $eol
                  . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $name . '"' . $eol
                  . 'Content-Transfer-Encoding: binary'.$eol;
        
            $data .= $eol;
            $data .= $content . $eol;
        }
        $data .= "--" . $delimiter . "--".$eol;
    
        return $data;
    }
}
