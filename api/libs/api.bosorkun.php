<?php

class Bosorkun extends WolfDispatcher {

    /**
     * Contains system alter config as key=>value
     *
     * @var array
     */
    protected $altCfg = array();

    /**
     * Current instance authorization state
     *
     * @var bool
     */
    protected $loggedIn = false;

    /**
     * Current conversation user login
     *
     * @var string
     */
    protected $myLogin = '';

    /**
     * Current conversation user password md5 hash
     *
     * @var string
     */
    protected $myPassword = '';

    /**
     * Current conversation client chatId
     *
     * @var int
     */
    protected $chatId = '';

    /**
     * Remote Ubilling userstats URL
     *
     * @var string
     */
    protected $apiUrl = '';

    /**
     * System caching object placeholder
     *
     * @var object
     */
    protected $cache = '';

    /**
     * Default authorization timeout
     *
     * @var int
     */
    protected $cacheTimeout = 31104000; //1 year i guess

    public function __construct($token) {
        $this->loadConfigs();
        $this->setOptions();
        $this->initCache();
        if (!empty($token)) {
            $this->botToken = $token;
        }
        $this->initTelegram();
    }

    /**
     * Preloads all of required configs
     * 
     * @global object $ubillingConfig
     * 
     * @return void
     */
    protected function loadConfigs() {
        global $ubillingConfig;
        $this->altCfg = $ubillingConfig->getAlter();
    }

    /**
     * Inits system cache instance
     * 
     * @return void
     */
    protected function initCache() {
        $this->cache = new UbillingCache();
    }

    /**
     * Sets all of required options from config values
     * 
     * @return void
     */
    protected function setOptions() {
        $this->apiUrl = $this->altCfg['API_URL'];
    }

    /**
     * Registers new web-hook url for bot
     * 
     * @param string $url
     * 
     * @return array
     */
    public function registerHook($url) {
        return($this->telegram->setWebHook($url));
    }

    /**
     * Empty actions handler prototype
     *
     * @return void
     */
    protected function handleEmptyAction() {
        //nothing to see here
    }

    /**
     * Checks is some login/password auth valid or not?
     * 
     * @param string $login
     * @param string $password
     * 
     * @return bool
     */
    protected function checkAuth($login, $password) {
        $result = false;
        if (!empty($login) AND ! empty($password)) {
            $url = $this->apiUrl . '/?xmlagent=true&json=true&uberlogin=' . $login . '&uberpassword=' . $password;
            $api = new OmaeUrl($url);
            $reply = $api->response();
            if (!empty($reply)) {
                $replyDec = json_decode($reply, true);
                if (is_array($replyDec)) {
                    if (!empty($replyDec)) {
                        $result = true;
                    }
                }
            }
        }
        return($result);
    }

    /**
     * Just sends some string content to current conversation
     * 
     * @param string $data
     * 
     * @return void
     */
    protected function sendToUser($data) {
        if (!empty($this->chatId)) {
            $this->telegram->directPushMessage($this->chatId, $data);
        }
    }

    /**
     * Loads previously stored auth data for current conversation
     * 
     * @return void
     */
    protected function loadAuthData() {
        $authData = $this->cache->get('AUTH_' . $this->chatId, $this->cacheTimeout);
        if (empty($authData)) {
            $authData = array('login' => '', 'password' => '');
        } else {
            $this->myLogin = $authData['login'];
            $this->myPassword = $authData['password'];
        }
    }

    /**
     * Setups some user auth data and stores it into cache
     * 
     * @return void
     */
    protected function actionLogIn() {
        if (!empty($this->chatId)) {
            if (!$this->loggedIn) {
                $authData = $this->cache->get('AUTH_' . $this->chatId, $this->cacheTimeout);
                if (empty($authData)) {
                    $authData = array('login' => '', 'password' => '');
                }

                if (!ispos($this->receivedData['text'], '/start')) {
                    if (empty($this->myLogin)) {
                        if (empty($this->myLogin) AND  $this->receivedData['text'] != __('Sign out')) {
                            $this->sendToUser(__('Enter your login'));
                        }
                        if (!ispos($this->receivedData['text'], '/start') AND $this->receivedData['text'] != __('Sign in') AND $this->receivedData['text'] != __('Sign out')) {
                            if (!empty($this->receivedData['text'])) {
                                $authData['login'] = trim($this->receivedData['text']);
                                $this->myLogin = $authData['login'];
                                $this->cache->set('AUTH_' . $this->chatId, $authData, $this->cacheTimeout);
                                $this->sendToUser(__('Your login') . ': ' . $this->myLogin);
                                $this->sendToUser(__('Enter your password'));
                            }
                        }
                    } else {
                        if (empty($this->myPassword)) {
                            if (!ispos($this->receivedData['text'], '/start') AND $this->receivedData['text'] != __('Sign in') AND $this->receivedData['text'] != __('Sign out')) {
                                if (!empty($this->receivedData['text'])) {
                                    $rawPassword = trim($this->receivedData['text']);
                                    if (!empty($rawPassword)) {
                                        $authData['password'] = md5($rawPassword);
                                        $this->myPassword = $authData['password'];
                                        $this->cache->set('AUTH_' . $this->chatId, $authData, $this->cacheTimeout);
                                        $this->sendToUser(__('Your password') . ': ' . $rawPassword);
                                        if ($this->checkAuth($this->myLogin, $this->myPassword)) {
                                            $this->loggedIn = true;
                                            $userData = $this->getApiData();
                                            $this->sendToUser(__('Welcome') . ', ' . $userData['realname']);
                                            $this->actionKeyboard(__('Whats next') . '?');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Cleanups user auth data
     * 
     * @return void
     */
    protected function actionLogout() {
        if (!empty($this->chatId)) {
            $this->cache->delete('AUTH_' . $this->chatId);
            $this->loggedIn = false;
            $this->sendToUser(__('You are logged off now'));
            $this->actionKeyboard(__('Sign in') . '?');
        }
    }

    /**
     * Pushes some keyboard to uses based on current context
     * 
     * @param string $title
     * 
     * @return void
     */
    protected function actionKeyboard($title = '') {
        if (empty($title)) {
            $title = '⌨️';
        }

        if (!$this->loggedIn) {
            if (!empty($this->receivedData['text'])) {
                if (ispos($this->receivedData['text'], '/start') AND ispos($this->receivedData['text'], '-')) {

                    $rawAuth = str_replace('/start', '', $this->receivedData['text']);
                    $rawAuth = explode('-', $rawAuth);
                    if (!empty($rawAuth[0]) AND ! empty($rawAuth[1])) {
                        $tryLogin = trim($rawAuth[0]);
                        $tryPassword = trim($rawAuth[1]);
                        if ($this->checkAuth($tryLogin, $tryPassword)) {
                            $this->myLogin = $tryLogin;
                            $this->myPassword = $tryPassword;
                            $this->loggedIn = true;
                            $authData = array(
                                'login' => $this->myLogin,
                                'password' => $this->myPassword
                            );
                            $this->cache->set('AUTH_' . $this->chatId, $authData, $this->cacheTimeout);
                            $this->sendToUser(__('You are successfully signed in') . '. ' . __('Welcome') . '!');
                        }
                    }
                }
            }
        }


        if ($this->loggedIn) {
            $buttonsArray[] = array(__('Profile'), __('Credit'), __('Pay'), __('Sign out'));
            $oneTime = false;
        } else {

            $buttonsArray[] = array(__('Sign in'), __('Sign out'));
            $oneTime = true;
        }

        $keyboard = $this->telegram->makeKeyboard($buttonsArray, false, true, $oneTime);
        $this->telegram->directPushMessage($this->chatId, $title, $keyboard);
    }

    /**
     * Just hook input data listener
     * 
     * @return array
     */
    public function listen() {
        $this->receivedData = $this->telegram->getHookData();
        if (!empty($this->receivedData)) {
            $this->chatId = $this->receivedData['chat']['id'];
            $this->loadAuthData();
            if ($this->checkAuth($this->myLogin, $this->myPassword)) {
                $this->loggedIn = true;
            }
            if (!$this->loggedIn AND ( empty($this->myLogin) OR empty($this->myPassword))) {
                $this->actionLogIn();
            }

            $this->reactInput();
        }
        return($this->receivedData);
    }

    /**
     * Pushes some request to Ubilling userstats XMLAgent API
     * 
     * @param string $request
     * 
     * @return array
     */
    protected function getApiData($request = '') {
        $result = array();
        if ($this->loggedIn) {
            $fullUrl = $this->apiUrl . '?xmlagent=true&json=true&uberlogin=' . $this->myLogin . '&uberpassword=' . $this->myPassword . $request;
            $remoteApi = new OmaeUrl($fullUrl);
            $requestData = $remoteApi->response();
            if (!empty($requestData)) {
                @$requestData = json_decode($requestData, true);
                if (is_array($requestData)) {
                    $result = $requestData;
                }
            }
        }
        return($result);
    }

    /**
     * Requsts or checks possibility of user credits via XMLAgent API
     * 
     * @param string $request
     * 
     * @return array
     */
    protected function getCreditData($request = '') {
        $result = array();
        if ($this->loggedIn) {
            $fullUrl = $this->apiUrl . '?module=creditor&agentcredit=true&json=true&uberlogin=' . $this->myLogin . '&uberpassword=' . $this->myPassword . $request;
            $remoteApi = new OmaeUrl($fullUrl);
            $requestData = $remoteApi->response();
            if (!empty($requestData)) {
                @$requestData = json_decode($requestData, true);
                if (is_array($requestData)) {
                    $result = $requestData;
                }
            }
        }
        return($result);
    }

    /**
     * Renders user profile data
     * 
     * @return void
     */
    public function actionProfile() {
        $userData = $this->getApiData();
        if (!empty($userData)) {
            //$this->sendToUser(print_r($userData, true));

            $reply = __('Welcome') . ', ' . $userData['realname'] . '!' . PHP_EOL;
            $reply .= __('Address') . ': ' . $userData['address'] . PHP_EOL;
            $reply .= __('Your') . ' ' . __('tariff') . ': ' . $userData['tariff'] . PHP_EOL;
            $reply .= __('Your') . ' ' . __('balance') . ': ' . $userData['cash'] . ' ' . $userData['currency'] . PHP_EOL;
            $reply .= __('Your') . ' ' . __('credit') . ': ' . $userData['credit'] . ' ' . $userData['currency'] . PHP_EOL;
            if ($userData['creditexpire'] != 'No') {
                $reply .= __('Credit') . ' ' . __('till') . ': ' . $userData['creditexpire'] . ' ' . $userData['currency'] . PHP_EOL;
            }

            $reply .= __('Your') . ' ' . __('account') . ': ' . __($userData['accountstate']) . PHP_EOL;
            $reply .= __('Your') . ' ' . __('payment ID') . ': ' . $userData['payid'] . PHP_EOL;

            $this->sendToUser($reply);
            $this->actionKeyboard(__('Whats next') . '?');
        }
    }

    /**
     * Requests credit for user
     * 
     * @return void
     */
    public function actionCredit() {
        if ($this->loggedIn) {
            $onlyTest = '';
            //$onlyTest='&justcheck=true';
            $creditCheck = $this->getCreditData($onlyTest);
            if ($creditCheck['status'] == 0) {
                $this->sendToUser(__('Credit succefully set'));
            } else {
                $this->sendToUser(__($creditCheck['message']));
            }
            $this->actionKeyboard(__('Whats next') . '?');
        }
    }

    /**
     * Renders payment methods available for user
     * 
     * @return void
     */
    public function actionOpenpayz() {
        if ($this->loggedIn) {
            $allData = $this->getApiData('&opayz=true');
            if (!empty($allData)) {
                $buttonsArray = array();
                foreach ($allData as $io => $each) {
                    $buttonsArray[] = array(array('text' => $each['name'], 'url' => $each['url']));
                }

                $keyboard = $this->telegram->makeKeyboard($buttonsArray, true, true, false);
                $this->telegram->directPushMessage($this->chatId, __('Available payment methods'), $keyboard);
            }
            //$this->actionKeyboard(__('Whats next') . '?');
        }
    }

}
