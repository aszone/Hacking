<?php

namespace Aszone\Hacking;

use GuzzleHttp\Client;
use Respect\Validation\Validator as v;
use Symfony\Component\DomCrawler\Crawler;
use Aszone\FakeHeaders\FakeHeaders;

//use Aszone\Site;

class WordPress
{
    public $target;

    public $proxy;

    public $portProxy;

    public $tor;

    public $pathPluginJson;

    public $torForGuzzle;

    public $commandData;

    /**
     * @param string $proxy
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * @param string $portProxy
     */
    public function setPortProxy($portProxy)
    {
        $this->portProxy = $portProxy;
    }

    /**
     * @param string $tor
     */
    public function setTor($tor = '127.0.0.1:9050')
    {
        $this->tor = $tor;
        $this->torForGuzzle = ['proxy' => [
            'http' => 'socks5://127.0.0.1:9050',
            'https' => 'socks5://127.0.0.1:9050',
        ]];
    }

    public function __construct($commands=array())
    {
        $this->optionTor = array();
        $this->installPlugin();
        $this->torForGuzzle = false;

        //Check command of entered.
        $defaultEnterData = $this->defaultEnterData();
        $this->commandData = array_merge($defaultEnterData, $commands);
        if ($this->commandData['torl']) {
            $this->commandData['tor'] = $this->commandData['torl'];
        }
    }

    private function defaultEnterData()
    {
        $dataDefault['dork'] = false;
        $dataDefault['pl'] = false;
        $dataDefault['tor'] = false;
        $dataDefault['torl'] = false;
        $dataDefault['virginProxies'] = false;
        $dataDefault['proxyOfSites'] = false;

        return $dataDefault;
    }

    public function setTarget($target){
        $this->target = $target;
    }

    //VERIFY IF IS WORDPRESS
    public function isWordPress()
    {
        $isUrl = v::url()->notEmpty()->validate($this->target);
        if ($isUrl) {
            $baseUrlWordPress = $this->getBaseUrlWordPressCrawler();
            if ($baseUrlWordPress) {
                return true;
            }

            return false;
        }
    }

    public function getBaseUrlWordPressByUrl()
    {
        $arrUrl=parse_url($this->target);
        if($arrUrl['path']=="/"){
            return $this->target;
        }
        $validXmlrpc = preg_match("/(.+?)((wp-content\/themes|wp-content\/plugins|wp-content\/uploads)|xmlrpc.php|feed\/|comments\/feed\/|wp-login.php|wp-admin).*/", $this->target, $m, PREG_OFFSET_CAPTURE);
        if ($validXmlrpc) {
            return $m[1][0];
        } else {
            $header = new FakeHeaders();
            try {
                $client = new Client(['defaults' => [
                    'headers' => ['User-Agent' => $header->getUserAgent()],
                    'proxy' => $this->commandData['tor'],
                    'timeout' => 30,
                ],
                ]);
                $body = $client->get($this->target)->getBody()->getContents();
                $crawler = new Crawler($body);
                $arrLinks = $crawler->filter('script');
                foreach ($arrLinks as $keyLink => $valueLink) {
                    $validXmlrpc = preg_match("/(.+?)((wp-content\/themes|wp-content\/plugins|wp-content\/uploads)|xmlrpc.php|feed\/|comments\/feed\/|wp-login.php|wp-admin).*/", substr($valueLink->getAttribute('resource'), 0), $m, PREG_OFFSET_CAPTURE);
                    if ($validXmlrpc) {
                        return $m[1][0];
                    }
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }



    public function getBaseUrlWordPressCrawler()
    {
        $targetTests[0] = $this->getBaseUrlWordPressByUrl();
        $targetTests[1] = $targetTests[0].'wp-login.php';

        $header = new FakeHeaders();
        foreach ($targetTests as $keyTarget => $targetTest) {
            try {
                $client = new Client(['defaults' => [
                    'headers' => ['User-Agent' => $header->getUserAgent()],
                    'proxy' => $this->commandData['tor'],
                    'timeout' => 30,
                ],
                ]);
                $res = $client->get($targetTest);

                //Check status block
                $body = $res->getBody()->getContents();

                $crawler = new Crawler($body);

                $arrLinks = $crawler->filter('script');
                foreach ($arrLinks as $keyLink => $valueLink) {
                    $validHref = $valueLink->getAttribute('src');
                    if (!empty($validHref)) {
                        $validXmlrpc = preg_match("/(.+?)(wp-content\/themes|wp-content\/plugins|wp-includes\/).*/", $validHref, $matches, PREG_OFFSET_CAPTURE);

                        if ($validXmlrpc) {
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                //echo "Error code ".$e->getCode()." => ".$e->getMessage();

                return false;
            }
        }
    }
    public function checkBlockedTime($html)
    {
        $pos3 = strpos($html, 'Account blocked for');
        if ($pos3) {
            $validResult = preg_match("/<span id=\"secondsleft\">(.*)<\/span>/", $html, $m, PREG_OFFSET_CAPTURE);
            if ($validResult) {
                return $m[1][0];
            }
        }

        return false;
    }

    public function validateLogon($html)
    {
        $pos = strpos($html['body'], '<strong>ERRO</strong>');
        $pos2 = strpos($html['body'], '<strong>ERROR</strong>');
        $pos3 = strpos($html['body'], 'Account blocked for');
        $pos4 = strpos($html['status']['url'], 'wp-admin');

        //in future check timeout
        if (($pos !== false or $pos2 !== false or $pos3 !== false)) {
            return false;
        }
        if ($pos4 === false) {
            return false;
        }

        return true;
    }

    public function getRootUrl()
    {
    }

    public function getUsers($limitNumberUsers = 99999)
    {
        $baseUrlWordPress = $this->getBaseUrlWordPressByUrl($this->target);

        $userList = array();
        //Number for validade finish list of user
        $emptySequenceUsers = 0;
        $header = new FakeHeaders();
        for ($i = 1; $i <= $limitNumberUsers; ++$i) {
            try {
                $client = new Client(['defaults' => [
                    'headers' => ['User-Agent' => $header->getUserAgent()],
                    'proxy' => $this->commandData['tor'],
                    'timeout' => 30,
                ],
                ]);
                $result = $client->get($baseUrlWordPress.'/?author='.$i);

                //Check status block
                $validGetUserByUrl = preg_match("/(.+?)\/\?author=".$i.'/', substr($result->getEffectiveUrl(), 0), $matches, PREG_OFFSET_CAPTURE);

                if (!$validGetUserByUrl) {
                    $username = $this->getUserByUrl($result->getEffectiveUrl());
                } else {
                    $username = $this->getUserBytagBody($result->getBody()->getContents());
                }
                if (!empty($username)) {
                    $userList[] = str_replace('-', ' ', $username);
                    echo $username;
                    echo ' | ';
                    $emptySequenceUsers = 0;
                } else {
                    if ($limitNumberUsers == 99999) {
                        ++$emptySequenceUsers;
                        echo ' | Sequence empty ';
                        if ($emptySequenceUsers == 10) {
                            return $userList;
                        }
                    }
                }
            } catch (\Exception $e) {
                if ($limitNumberUsers == 99999) {
                    ++$emptySequenceUsers;
                    echo ' | Sequence empty ';
                    if ($emptySequenceUsers == 10) {
                        return $userList;
                    }
                }
            }
        }

        return $userList;
    }

    protected function getUserBytagBody($body)
    {
        $crawler = new Crawler($body);
        $bodys = $crawler->filter('body');
        foreach ($bodys as $keyBody => $valueBody) {
            $class = $valueBody->getAttribute('class');
        }
        $username = preg_match("/author-(.+?)\s/", substr($class, 0), $matches, PREG_OFFSET_CAPTURE);
        if (isset($matches[1][0]) and (!empty($matches[1][0]))) {
            return $matches[1][0];
        }

        return false;
    }

    protected function getUserByUrl($urlUser)
    {
        $validUser = preg_match("/author\/([\d\w-@\.%]+)/", substr($urlUser, 0), $matches, PREG_OFFSET_CAPTURE);

        if (isset($matches[1][0]) and (!empty($matches[1][0]))) {
            return $matches[1][0];
        }

        return false;
    }

    public function getPlugins()
    {
    }

    public function getPluginsVullExpert()
    {
        $jsonPlugins = $this->getListPluginsVull();
        //verify if plugins in list of vull
        foreach ($jsonPlugins as $keyPlugin => $plugin) {
            $validPlugin = $this->checkPluginExpert($keyPlugin);
            echo $keyPlugin.' | ';
            if ($validPlugin) {
                $arrPlugin[$keyPlugin] = $plugin;
                $arrPlugin[$keyPlugin]['url'] = $this->target.'/wp-content/plugins/'.$keyPlugin;
            }
        }
        //Verify W3 total cache and Wp Super cache using detectable active because wpscan has
        // unsing guzzle
        // example http://exempla.com/wp-content/plugins/wp-super-cache/
        return $arrPlugin;
    }

    public function getPluginsVull()
    {
        try {
            $arrPluginsVull = array();
            $client = new Client();
            $res = $client->get($this->target, $this->commandData['tor']);
            //check if is block
            $body = $res->getBody()->getContents();
            $crawler = new Crawler($body);
            $arrLinksLink = $crawler->filter('link');
            $arrLinksScript = $crawler->filter('script');

            //find href on links of css
            foreach ($arrLinksLink as $keyLink => $valueLink) {
                if (!empty($valueLink->getAttribute('href'))) {
                    $arryUrls[] = $valueLink->getAttribute('href');
                }
            }

            //find resource on scripts of js
            foreach ($arrLinksScript as $keyScript => $valueScript) {
                if (!empty($valueScript->getAttribute('resource'))) {
                    $arryUrls[] = $valueScript->getAttribute('resource');
                }
            }

            //extract only name of plugin
            $arrPlugins = array();
            foreach ($arryUrls as $urls) {
                $validUrlPlugins = preg_match("/\/wp-content\/plugins\/(.+?)\//", substr($urls, 0), $matches, PREG_OFFSET_CAPTURE);
                if ($validUrlPlugins) {
                    $arrPlugins[] = $matches[1][0];
                }
            }

            //clean plugin repated
            $arrPlugins = array_unique($arrPlugins);

            //return listOfPluginsVull
            $jsonPlugins = $this->getListPluginsVull();

            //Equals list of site with list of all plugins vull
            foreach ($arrPlugins as $plugin) {
                if (array_key_exists($plugin, $jsonPlugins)) {
                    $arrPluginsVull[$plugin] = $jsonPlugins[$plugin];
                }
            }
        } catch (\Exception $e) {
            $arrPluginsVull = array();
        }

        return $arrPluginsVull;
    }

    public function getThemes()
    {
    }

    private function checkPluginExpert($plugin)
    {
        try {
            $url = $this->target.'/wp-content/plugins/'.$plugin;
            $client = new Client();
            $res = $client->get($url, $this->commandData['tor']);
            //check if change new tor ip
            $status = $res->getStatusCode();
            if (!$status == 200) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function installPlugin()
    {
        $pathDataZip = __DIR__ . '/resource/data.zip';
        $pathFolderTmp = __DIR__ . '/resource/tmp/';
        $this->pathPluginJson = __DIR__ . '/resource/tmp/data/plugins.json';
        if (!file_exists($this->pathPluginJson)) {
            $zip = new \ZipArchive();
            $zip->open($pathDataZip);
            $zip->extractTo($pathFolderTmp);
            $zip->close();
        }
    }

    private function getListPluginsVull()
    {
        $htmlPlugin = file_get_contents($this->pathPluginJson);
        $jsonPlugins = json_decode($htmlPlugin, true);
        ksort($jsonPlugins);

        return $jsonPlugins;
    }
    public function isHttps($url)
    {
        $isValidate = preg_match("/^https:\/\//", $url, $m, PREG_OFFSET_CAPTURE);
        if ($isValidate) {
            return $isValidate;
        }

        return;
    }


}
