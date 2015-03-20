<?php

/**
 * Class YouHosting
 * A PHP wrapper around the official API provided by YouHosting. Requires a SuperVIP account.
 */
class YouHosting {
    private $config = array(
        'continueIfNoResponse' => false,
    );
    private $host = "https://rest.main-hosting.com";

    /**
     * Create a new YouHosting object
     * @param string $apikey your YouHosting API key
     * @param array $config an array with configuration values: {continueIfNoResponse: true/false}
     * @param bool $continueIfNoResponse boolean to determine what should be done when there is no response from YouHosting (which happens and is usually safe to ignore)
     */
    public function __construct($apikey, $config){
        $this->apikey = $apikey;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Perform a GET request
     * @param $url the URL to request
     * @return mixed the result json data
     * @throws YouHostingException if the connection fails, this exception is thrown
     */
    private function get($url){
        do {
            $ch = curl_init($this->host . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, "reseller:" . $this->apikey);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $return = curl_exec($ch);
            curl_close($ch);

            if(empty($return) && !$this->config['continueIfNoResponse']){
                throw new YouHostingException(array('code' => 0, 'message' => 'Empty response from YouHosting'));
            }
        } while (empty($return) && $this->config['continueIfNoResponse']);

        $data = json_decode($return);

        if(!empty($data->error)){
            throw new YouHostingException($data->error);
        }

        return $data->result;
    }

    /**
     * Perform a POST request
     * @param $url the URL to request
     * @param $data (optional) post data to add
     * @return mixed the result json data
     * @throws YouHostingException if the connection fails, this exception is thrown
     */
    private function post($url, $data = null){
        do {
            $ch = curl_init($this->host.$url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            if(!empty($data)){
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
            }
            curl_setopt($ch, CURLOPT_USERPWD, "reseller:".$this->apikey);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $return = curl_exec($ch);
            curl_close($ch);

            if(empty($return) && !$this->config['continueIfNoResponse']){
                throw new YouHostingException(array('code' => 0, 'message' => 'Empty response from YouHosting'));
            }
        } while (empty($return) && $this->config['continueIfNoResponse']);

        $data = json_decode($return);

        if(!empty($data->error)){
            throw new YouHostingException($data->error);
        }

        return $data->result;
    }

    /**
     * Perform a DELETE request
     * @param $url the URL to request
     * @return mixed the result json data
     * @throws YouHostingException if the connection fails, this exception is thrown
     */
    private function delete($url){
        do{
            $ch = curl_init($this->host.$url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_USERPWD, "reseller:".$this->apikey);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $return = curl_exec($ch);
            curl_close($ch);

            if(empty($return) && !$this->config['continueIfNoResponse']){
                throw new YouHostingException(array('code' => 0, 'message' => 'Empty response from YouHosting'));
            }
        } while (empty($return) && $this->config['continueIfNoResponse']);

        $data = json_decode($return);

        if(!empty($data->error)){
            throw new YouHostingException($data->error);
        }

        return $data->result;
    }

    /**
     * Checks the connection to the API server
     * @return bool whether the connection was succesful
     * @throws YouHostingException
     */
    public function ping(){
        return $this->get("/ping") == "pong";
    }

    /**
     * Create a new client
     * @param $data an array of data, containing {first_name, email, password, captcha_id}
     * @return int the ID of the new client
     * @throws YouHostingException
     */
    public function clientCreate($data){
        return $this->post("/v1/client", $data)->id;
    }

    /**
     * List all clients of the reseller
     *
     * WARNING: don't use this if you have a lot of clients, there is a connection limit which can break an import of many accounts
     *
     * @param int $page (optional) if you don't want to start at page one (to resume a partially completed pull)
     * @return array an array of clients
     * @throws YouHostingException
     */
    public function clientList($page = 1){
        return $this->get("/v1/client/list?page=".$i);

        $totalPages = PHP_INT_MAX;
        $clients = array();

        for($i = $page; $i <= $totalPages; $i++){
            $data = $this->get("/v1/client/list?page=".$i);

            $totalPages = $data->pages;

            $clients = array_merge($clients, $data->list);
        }

        return $clients;
    }

    /**
     * List all clients of the reseller
     * This particular
     *
     *
     * @param callable $userFunction a function which can process an array of clients
     * @throws YouHostingException
     */
    public function clientListCallable(callable $userFunction){
        $totalPages = PHP_INT_MAX;

        for($i = 1; $i <= $totalPages; $i++){
            $data = $this->get("/v1/client/list?page=".$i);

            $totalPages = $data->pages;

            $userFunction($data->list);
        }
    }

    /**
     * Get the details of a client
     * @param $client_id the ID of the client
     * @return array
     * @throws YouHostingException
     */
    public function clientGet($client_id){
        return $this->get("/v1/client/".$client_id);
    }

    /**
     * Get a one time URL to login to the client's account
     * @param $client_id
     * @return string
     * @throws YouHostingException
     */
    public function clientLoginUrl($client_id){
        return $this->get("/v1/client/".$client_id."/login-url");
    }

    /**
     * Check if a domain is available to use
     * @param string $type "domain" or "subdomain"
     * @param string $domain if type is "domain", then this is the domain to check. if type is "subdomain", this is the master domain
     * @param string $subdomain if the type is "subdomain", this is the string the client would like to use as his own subdomain
     * @return bool whether the domain is available
     * @throws YouHostingException
     */
    public function accountCheck($type, $domain, $subdomain = ""){
        $postdata = array(
            'type' => $type,
            'domain' => $domain,
        );
        if(!empty($subdomain)){
            $postdata['subdomain'] = $subdomain;
        }

        return $this->post($url, $postdata);
    }

    /**
     * Create a new account
     * @param $data account data {client_id, captcha_id, plan_id, type (see accountCheck), domain (see accountCheck), subdomain (see accountCheck), password }
     * @return mixed
     * @throws YouHostingException
     */
    public function accountCreate($data){
        return $this->post("/v1/account", $data);
    }

    /**
     * Get a list of account data
     * @param int $page (optional) if you don't want to start at page one (to resume a partially completed pull)
     * @param null $client_id (optional) if you want to get the accounts for a specific client, you can specify a client id
     * @return array a list of accounts
     * @throws YouHostingException
     */
    public function accountList($page = 1, $client_id = null){
        $totalPages = PHP_INT_MAX;
        $accounts = array();

        for($i = $page; $i <= $totalPages; $i++){
            $url = "/v1/account/list?page=".$i;
            if(!empty($client_ip)){
                $url .= "&client_ip=".$client_id;
            }

            try{
                $data = $this->get($url);
            } catch (YouHostingException $e){
                throw $e;
            }

            $totalPages = $data->pages;

            $accounts = array_merge($accounts, $data->list);
        }

        return $accounts;
    }

    /**
     * get the details of an account
     * @param $account_id an account id
     * @return array
     * @throws YouHostingException
     */
    public function accountGet($account_id){
        return $this->get("/v1/account/".$account_id);
    }

    /**
     * suspend an account
     * @param $account_id
     * @return bool whether the action was successful
     * @throws YouHostingException
     */
    public function accountSuspend($account_id){
        return $this->post("/v1/account/".$account_id."/suspend", array(
            'id' => $account_id
        ));
    }

    /**
     * unsuspend an account
     * @param $account_id
     * @return bool whether the action was successful
     * @throws YouHostingException
     */
    public function accountUnsuspend($account_id){
        return $this->post("/v1/account/".$account_id."/unsuspend", array(
            'id' => $account_id
        ));
    }

    /**
     * Get a one time URL to login to the account
     * @param $account_id
     * @return string
     * @throws YouHostingException
     */
    public function accountLoginUrl($account_id){
        return $this->get("/v1/account/".$account_id."/login-url");
    }

    /**
     * delete the account
     * @param $account_id
     * @return bool
     * @throws YouHostingException
     */
    public function accountDelete($account_id){
        return $this->delete("/v1/account/".$account_id);
    }

    /**
     * Get a new captcha
     * @return array a response array containing an id and url
     * @throws YouHostingException
     */
    public function newCaptcha(){
        return $this->post("/v1/captcha");
    }

    /**
     * Solve the captcha
     * @param $captcha_id the ID of the captcha
     * @param $solution the solution provided by the user
     * @return bool whether the answer is correct or nor
     * @throws YouHostingException
     */
    public function checkCaptcha($captcha_id, $solution){
        $result = $this->post("/v1/captcha/".$captcha_id, array(
            'id' => $captcha_id,
            'solution' => $solution,
        ));

        return $result->solved;
    }

    /**
     * List the domains for which clients can create subdomains
     * @return array
     * @throws YouHostingException
     */
    public function subdomains(){
        return $this->get("/v1/settings/subdomains");
    }

    /**
     * List the plans for this reseller account
     * @return array
     * @throws YouHostingException
     */
    public function plans(){
        return $this->get("/v1/settings/plans");
    }

    /**
     * Get the nameservers
     * @return array an array with ns1, ..., ns4 and ip1, ... ip4
     * @throws YouHostingException
     */
    public function nameservers(){
        return $this->get("/v1/settings/nameservers");
    }
}

/**
 * Class YouHostingException
 * An exception class for the YouHosting wrapper
 */
class YouHostingException extends Exception {
    public function __construct($error){
        if(is_array($error)){
            parent::__construct($error['message'], $error['code']);
        } else {
            parent::__construct($error->message, $error->code);
        }
    }
}
