<?php
/**
 * Prosperent API Class
 *
 * The Prosperent API Class was developed to simplify the process of
 * connecting to the Prosperent API and parsing the results.
 *
 * NOTE: This PHP code relies on PHP 5.2 and above.
 *
 * Using the API is very simple.  Instantiate the class with the available properties
 * and fetch() the results:
 *
 * <code>
 * <?php
 * require_once('Prosperent_Api.php');
 *
 * $prosperentApi = new Prosperent_Api(array(
 *     'api_key'    => '<enter a valid api key here>',
 *     'query'      => '<enter the search query here>',
 *     'visitor_ip' => $_SERVER['REMOTE_ADDR'],
 *     'userAgent'  => $_SERVER['HTTP_USER_AGENT'],
 *     //if you are tracking by channel, enter your created channel id here
 *     'channel_id' => 0,
 *     //if you want to use pagination, use the following arguments
 *     'page'       => 1,
 *     'limit'      => 25
 *     //if you want to track your domains as an sid, or any other valid
 *     //string as an sid, set it here
 *     'sid'        => $_SERVER['HTTP_HOST']
 * ));
 *
 * //it is recommended that you log impressions separately from your
 * //search requests, this way you can cache API results, but still
 * //log accurate impression data. Note: errors and warnings can
 * //still occur during a log call, so it is wise to log them
 *
 * $prosperentApi->log();
 *
 * //log warnings or errors
 * if ($prosperentApi->hasErrors() || $prosperentApi->hasWarnings())
 * {
 *     //log warnings and errors
 * }
 *
 * //this array will contain whether the user agent was recognized
 * //as a bot, and what kind of bot it was (Google, Yahoo!, etc)
 * $logResponse = $prosperentApi->getData();
 *
 * //now you can immediately call the fetch method to get the product data
 * $prosperentApi->fetch();
 *
 * //check for errors
 * if ($prosperentApi->hasErrors()|| $prosperentApi->hasWarnings())
 * {
 *     //log warnings and errors
 * }
 *
 * //get the product data result
 * $data = $prosperentApi->getData();
 * ?>
 * </code>
 *
 * In the event that you need to determine the query from a SERP referrer, use the built-in
 * method when setting the "query" property:
 *
 * <code>
 * <?php
 * $prosperentApi->set_query(
 *     Prosperent_Api::getQueryFromReferrer($_SERVER['HTTP_REFERER'])
 * );
 *
 * $prosperentApi->fetch();
 * ?>
 * </code>
 *
 * If you want to put the API into debug mode while you test, to prevent the logging
 * of stats, set the API into debug mode with the following call:
 *
 * <code>
 * <?php
 * $prosperentApi->set_debugMode(true);
 * ?>
 * </code>
 *
 * If you need to use your own Exception handler besides the PHP default "Exception" class,
 * set the class name using this method:
 *
 * <code>
 * $prosperentApi->set_exceptionHandler('MyException');
 * </code>
 *
 * @author  Prosperent Mike
 * @version 1.0.4
 */

if (!function_exists('curl_init'))
{
  throw new Exception('Prosperent_Api needs the CURL PHP extension.');
}
if (!function_exists('json_decode'))
{
  throw new Exception('Prosperent_Api needs the JSON PHP extension.');
}

class Prosperent_Api
{
    //constants
    const VERSION = '1.1.0';

    /**
     * cURL options
     *
     * @var unknown_type
     */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5
    );

    /**
     * API Url
     * @var string
     */
    public static $api_url = 'http://api.prosperent.com/api/';

    /**
     * @var string
     */
    protected $_api_key;

    /**
     * @var string
     */
    protected $_query;

    /**
     * @var string
     */
    protected $_extendedQuery;

    /**
     * @var string
     */
    protected $_extendedSortMode;

    /**
     * @var string
     */
    protected $_visitor_ip;

    /**
     * @var string
     */
    protected $_userAgent;

    /**
     * @var int
     */
    protected $_channel_id = 0;

    /**
     * @var string
     */
    protected $_sid;

    /**
     * @var int
     */
    protected $_page = 1;

    /**
     * @var int
     */
    protected $_limit = 10;

    /**
     * @var bool
     */
    protected $_debugMode = false;

    /**
     * API Response Data Array
     *
     * @var null|array
     */
    protected $_data;

    /**
     * API Response Coupons Array
     *
     * @var null|array
     */
    protected $_coupons;

    /**
     * API Response Error Array
     *
     * @var null|array
     */
    protected $_errors;

    /**
     * API Response Warning Array
     * @var null|array
     */
    protected $_warnings;

    /**
     * The name of the class to throw exceptions with
     * @var string
     */
    protected $_exceptionHandler = 'Exception';

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct($properties=array())
    {
        //set defaults
        $properties['userAgent'] = (!$properties['userAgent'] ? $_SERVER['HTTP_USER_AGENT'] : $properties['userAgent']);
        $properties['visitor_ip'] = (!$properties['visitor_ip'] ? $_SERVER['REMOTE_ADDR'] : $properties['visitor_ip']);

        if (is_array($properties))
        {
            $this->setProperties($properties);
        }
    }

    /**
     * Set object state
     *
     * @param  array $properties
     * @return Default_Model_Users_User
     */
    public function setProperties(array $properties)
    {
        $methods = get_class_methods($this);
        foreach ($properties as $key => $value)
        {
            $method = 'set_' . $key;
            if (in_array($method, $methods))
            {
                $this->$method($value);
            }
        }
        return $this;
    }

    /**
     * Get all properties in array format
     *
     * @return array
     */
    public function getProperties()
    {
        $methods = get_class_methods($this);
        $propArray = array();
        foreach ($methods as $method)
        {
            if (preg_match('/^get_/', $method))
            {
                $propArray[str_replace('get_', '', $method)] = $this->$method();
            }
        }
        return $propArray;
    }

    /**
     * Logs an impression
     *
     * @return array
     */
    public function log()
    {
        //do we have the visitor IP?
        if (!$this->get_visitor_ip())
        {
            $eh = $this->get_exceptionHandler();
            throw new $eh('A visitor IP must be passed to the logger');
        }

        $url = $this->getUrl($this->getProperties(), 'log');

        return $this->makeRequest($url);
    }

    /**
     * Searches API and returns parsed JSON response
     *
     * @return array
     */
    public function fetch()
    {
        //do we have a query?
        if (!$this->get_query() && !$this->get_extendedQuery())
        {
            $eh = $this->get_exceptionHandler();
            throw new $eh('No query or extendedQuery was specified for the Prosperent API');
        }

        $url = $this->getUrl($this->getProperties());

        return $this->makeRequest($url);
    }

    /**
     * Makes an HTTP request to the Prosperent API
     *
     * @param  string $url
     * @return false|array
     */
    protected function makeRequest($url)
    {
        //init curl
        $ch = curl_init();

        //init options
        $opts = self::$CURL_OPTS;
        $opts[CURLOPT_URL] = $url;

        //disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
        // for 2 seconds if the server does not support this header.
        if (isset($opts[CURLOPT_HTTPHEADER]))
        {
            $existing_headers = $opts[CURLOPT_HTTPHEADER];
            $existing_headers[] = 'Expect:';
            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
        }
        else
        {
            $opts[CURLOPT_HTTPHEADER] = array('Expect:');
        }

        //set curl options
        curl_setopt_array($ch, $opts);

        //send request
        $result = curl_exec($ch);

        //check for false response
        if ($result === false)
        {
        	$errorString = curl_error($ch);
        	$errorCode = curl_errno($ch);
        	curl_close($ch);
       		throw new $this->_exceptionHandler($errorString, $errorCode);
        }

        //close curl
        curl_close($ch);

        //return false if the result is empty
        if (!strlen($result))
        {
            return false;
        }

        //parse result
        try
        {
            $result = json_decode($result, $array=true);

            if (is_array($result))
            {
                if (count($result['errors']))
                {
                    $this->_errors = $result['errors'];
                }

                if (count($result['warnings']))
                {
                    $this->_warnings = $result['warnings'];
                }

                $this->_data = $result['data'];

                $this->_coupons = $result['coupons'];
            }
        }
        catch (Exception $e)
        {
            $eh = $this->get_exceptionHandler();
            throw new $eh('The Prosperent API response could not be decoded: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Build the URL with the given parameters.
     *
     * @param array  $params
     * @param string $path
     */
    protected function getUrl($params=array(), $path='search')
    {
        $url = self::$api_url;

        //append class version with params
        $params['v'] = self::VERSION;

        //no need to send the handler
        unset($params['exceptionHandler']);

        //set the path
        if ($path)
        {
            if ($path[0] === '/')
            {
                $path = substr($path, 1);
            }

            $url .= $path;
        }

        if ($params)
        {
            $url .= '?' . http_build_query($params, null, '&');
        }

        return $url;
    }

    /**
     * Returns the data array from the API Response
     *
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns the coupons array from the API Response
     *
     * @return array
     */
    public function getCoupons()
    {
        return $this->_coupons;
    }

    /**
     * Returns any discovered errors
     *
     * @return null|array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Are there errors?
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (is_array($this->_errors) && count($this->_errors));
    }

    /**
     * Returns any discovered warnings
     *
     * @return null|array
     */
    public function getWarnings()
    {
        return $this->_warnings;
    }

    /**
     * Are there warnings?
     *
     * @return bool
     */
    public function hasWarnings()
    {
        return (is_array($this->_warnings) && count($this->_warnings));
    }

    /**
     * Get the name of the exception handler
     *
     * @return string
     */
    public function get_exceptionHandler()
    {
        return $this->_exceptionHandler;
    }

    /**
     * Sets the name of the exception handler to use
     *
     * @param  string $exceptionHandlerName
     * @return Prosperent_Api
     */
    public function set_exceptionHandler($exceptionHandlerName)
    {
        $this->_exceptionHandler = (string) $exceptionHandlerName;
        return $this;
    }

    /**
     * Get api key
     *
     * @return null|string
     */
    public function get_api_key()
    {
        return $this->_api_key;
    }

    /**
     * Set api key
     *
     * @param  string $api_key
     * @return Prosperent_Api
     */
    public function set_api_key($api_key)
    {
        $this->_api_key = (string) $api_key;
        return $this;
    }

    /**
     * Get query
     *
     * @return null|string
     */
    public function get_query()
    {
        return $this->_query;
    }

    /**
     * Set query
     *
     * @param  string $query
     * @return Prosperent_Api
     */
    public function set_query($query)
    {
        $this->_query = (string) $query;
        return $this;
    }

    /**
     * Get extendedQuery
     *
     * @return null|string
     */
    public function get_extendedQuery()
    {
        return $this->_extendedQuery;
    }

    /**
     * Set extendedQuery
     *
     * @param  string $extendedQuery
     * @return Prosperent_Api
     */
    public function set_extendedQuery($extendedQuery)
    {
        $this->_extendedQuery = (string) $extendedQuery;
        return $this;
    }

    /**
     * Get extendedSortMode
     *
     * @return null|string
     */
    public function get_extendedSortMode()
    {
        return $this->_extendedSortMode;
    }

    /**
     * Set extendedSortMode
     *
     * @param  string $extendedSortMode
     * @return Prosperent_Api
     */
    public function set_extendedSortMode($extendedSortMode)
    {
        $this->_extendedSortMode = (string) $extendedSortMode;
        return $this;
    }

    /**
     * Get visitor ip
     *
     * @return null|string
     */
    public function get_visitor_ip()
    {
        return $this->_visitor_ip;
    }

    /**
     * Set visitor ip
     *
     * @param  string $visitor_ip
     * @return Prosperent_Api
     */
    public function set_visitor_ip($visitor_ip)
    {
        $this->_visitor_ip = (string) $visitor_ip;
        return $this;
    }

    /**
     * Get user agent
     *
     * @return null|string
     */
    public function get_userAgent()
    {
        return $this->_userAgent;
    }

    /**
     * Set user agent
     *
     * @param  string $userAgent
     * @return Prosperent_Api
     */
    public function set_userAgent($userAgent)
    {
        $this->_userAgent = (string) $userAgent;
        return $this;
    }

    /**
     * Get channel id
     *
     * @return null|int
     */
    public function get_channel_id()
    {
        return $this->_channel_id;
    }

    /**
     * Set channel id
     *
     * @param  int $channel_id
     * @return Prosperent_Api
     */
    public function set_channel_id($channel_id)
    {
        $this->_channel_id = (int) $channel_id;
        return $this;
    }

    /**
     * Get sid
     *
     * @return null|string
     */
    public function get_sid()
    {
        return $this->_sid;
    }

    /**
     * Set sid
     *
     * @param  string $sid
     * @return Prosperent_Api
     */
    public function set_sid($sid)
    {
        $this->_sid = (string) $sid;
        return $this;
    }

    /**
     * Get page
     *
     * @return null|int
     */
    public function get_page()
    {
        return $this->_page;
    }

    /**
     * Set page
     *
     * @param  int $page
     * @return Prosperent_Api
     */
    public function set_page($page)
    {
        $this->_page = (int) $page;
        return $this;
    }

    /**
     * Get limit
     *
     * @return null|int
     */
    public function get_limit()
    {
        return $this->_limit;
    }

    /**
     * Set limit
     *
     * @param  int $limit
     * @return Prosperent_Api
     */
    public function set_limit($limit)
    {
        $this->_limit = (int) $limit;
        return $this;
    }

    /**
     * Get debugMode
     *
     * @return null|bool
     */
    public function get_debugMode()
    {
        return $this->_debugMode;
    }

    /**
     * Set debugMode
     *
     * @param  bool $debugMode
     * @return Prosperent_Api
     */
    public function set_debugMode($debugMode)
    {
        $this->_debugMode = (bool) $debugMode;
        return $this;
    }

    /**
     * Determines and returns the search query from the
     * SERP referrer
     *
     * @param  string $referrer
     * @return string
     */
    public static function getQueryFromReferrer($referrer)
    {
        $query = false;

        //clean the referrer
        $referer = trim(rawurldecode($referrer));
        $referer = preg_replace('/\\s/', '%20', $referer);

        //use the list of serp referrers
        $sr = array(
            array("q", "google"),
            array("q", "bing"),
            array("q", "search.msn"),
            array("q", "search.live"),
            array("q", "blogsearch.google"),
            array("q", "search.comcast"),
            array("q", "cuil"),
            array("query", "aolsearch.aol"),
            array("query", "aim.search.aol"),
            array("query", "search.aol"),
            array("query", "aolsearcht11.search.aol"),
            array("query", "search.hp.my.aol"),
            array("encquery", "search.aol"),
            array("query", "search.naver"),
            array("where", "search.naver"),
            array("p", "sq.search.yahoo"),
            array("p", "espanol.search.yahoo"),
            array("p", "ca.search.yahoo"),
            array("qid", "search.myway"),
            array("searchfor", "search.mywebsearch"),
            array("query", "search.netscape"),
            array("q", "toolbar.inbox"),
            array("q", "charter"),
            array("qs", "search.rr"),
            array("q", "int.ask"),
            array("q", "ask"),
            array("q", "charter"),
            array("qs", "search.rr"),
            array("p", "search.bt"),
            array("q", "aolsearcht5.search.aol"),
            array("query", "aim.search.aol"),
            array("query", "search.hp.my.aol"),
            array("p", "us.yhs.search.yahoo"),
            array("p", "search.bt"),
            array("q", "uk.ask"),
            array("q", "verizon"),
            array("q", "search.icq"),
            array("q", "search.conduit"),
            array("q", "search.incredimail"),
            array("q", "search.earthlink"),
            array("q", "suche.t-online"),
            array("q", "myembarq"),
            array("q", "search.sweetim"),
            array("query", "lo"),
            array("query", "search.cnn"),
            array("query", "aolsearcht3.search.aol"),
            array("query", "aolsearcht12.search.aol"),
            array("query", "aolsearcht10.search.aol"),
            array("query", "aolsearcht11.search.aol"),
            array("query", "aolsearcht2.search.aol"),
            array("query", "aolsearcht4.search.aol"),
            array("query", "aolsearcht5.search.aol"),
            array("query", "aolsearcht6.search.aol"),
            array("query", "aolsearcht7.search.aol"),
            array("query", "aolsearcht9.search.aol"),
            array("query", "tiscali.co"),
            array("q", "verden.abcsok"),
            array("query", "search.aol.co"),
            array("query", "univision"),
            array("q", "fastbrowsersearch"),
            array("q", "search.babylon"),
            array("q", "search.virginmedia"),
            array("as_q", "google"),
            array("q", "home.knology"),
            array("q", "search.pch"),
            array("term", "search1.sky"),
            array("q", "embarqmail"),
            array("q", "armstrongmywire"),
            array("find", "sensis.com"),
            array("q", "portal.tds"),
            array("q", "search.orange.co"),
            array("q", "search.alot"),
            array("q", "home.suddenlink"),
            array("qry", "searchservice.myspace"),
            array("q", "optimum"),
            array("q", "mypoints"),
            array("p", "search.yahoo")
        );

        if (strlen($referer) && preg_match('/^http:\/\//i', $referer) && $referrer = parse_url($referer))
        {
            @parse_str(preg_replace('/^\\?/', '', $referrer['query']), $referrerQueries);

            foreach ($sr as $s)
            {
                if (preg_match('/'.str_replace('.', '\\.', $s[1]).'\\.[a-z\\.]{2,6}$/i', $referrer['host']) && array_key_exists($s[0], $referrerQueries))
                {
                    $query = trim(rawurldecode(urldecode($referrerQueries[$s[0]])));
                }
            }
        }

        return $query;
    }
}