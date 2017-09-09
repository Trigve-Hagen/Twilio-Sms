<?php

class Global_Web_Service {
	public static $result;
}

class Services_Twilio_RestException
    extends Exception
{
    protected $status;
    protected $info;

    public function __construct($status, $message, $code = 0, $info = '')
    {
        $this->status = $status;
        $this->info = $info;
        parent::__construct($message, $code);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getInfo()
    {
        return $this->info;
    }
}

class ErrorLogToConsol{
	public static function console_log( $data ){
	  echo '<script>';
		echo 'console.log('. json_encode( $data ) .')';
	  echo '</script>';
	}
}

abstract class Services_Twilio_Resource
    implements Services_Twilio_DataProxy
{
    protected $name;
    protected $proxy;
    protected $subresources;

    public function __construct(Services_Twilio_DataProxy $proxy)
    {
        $this->subresources = array();
        $this->proxy = $proxy;
        $this->name = get_class($this);
        $this->init();
    }

    protected function init()
    {
        // Left empty for derived classes to implement
    }

    public function retrieveData($path, array $params = array())
    {
        return $this->proxy->retrieveData($path, $params);
    }

    public function deleteData($path, array $params = array())
    {
        return $this->proxy->deleteData($path, $params);
    }

    public function createData($path, array $params = array())
    {
        return $this->proxy->createData($path, $params);
    }

    public function getSubresources($name = null)
    {
        if (isset($name)) {
            return isset($this->subresources[$name])
                ? $this->subresources[$name]
                : null;
        }
        return $this->subresources;
    }

    public function addSubresource($name, Services_Twilio_Resource $res)
    {
        $this->subresources[$name] = $res;
    }

    protected function setupSubresources()
    {
        foreach (func_get_args() as $name) {
            $constantized = ucfirst(Services_Twilio_Resource::camelize($name));
            $type = "Services_Twilio_Rest_" . $constantized;
			/* $obj = new ErrorLogToConsol();
			$obj->console_log($type); */
            $this->addSubresource($name, new $type($this));
        }
    }

    public static function decamelize($word)
    {
		$pieces = preg_split('/(?=[A-Z])/',$word);
		if($pieces[0] == '') $string = strtolower($pieces[1]);
		else {
			$string = strtolower($pieces[0]);
			for($i=1; $i<count($pieces); $i++) $string .= '_'.strtolower($pieces[$i]);
		}
		return $string;
        /* $string = preg_replace(
            '/(^|[a-z])([A-Z])/e',
            'strtolower(strlen("\\1") ? "\\1_\\2" : "\\2")',
            $word
        ); */
    }

    public static function camelize($word)
    {
		$args = explode("_", $word); $string = '';
		foreach($args as $arg) $string .= ucfirst($arg);
        return $string; // preg_replace('/(^|_)([a-z])/e', 'strtoupper("\\2")', $word);
    }
}

interface Services_Twilio_DataProxy
{
    /**
     * Retrieve the object specified by key.
     *
     * @param string $key    The index
     * @param array  $params Optional parameters
     *
     * @return object The object
     */
    function retrieveData($key, array $params = array());

    /**
     * Create the object specified by key.
     *
     * @param string $key    The index
     * @param array  $params Optional parameters
     *
     * @return object The object
     */
    function createData($key, array $params = array());

    /**
     * Delete the object specified by key.
     *
     * @param string $key    The index
     * @param array  $params Optional parameters
     *
     * @return null
     */
    function deleteData($key, array $params = array());
}

class Services_Twilio_TinyHttpException extends ErrorException {}

class Services_Twilio_TinyHttp {
  var $user, $pass, $scheme, $host, $port, $debug, $curlopts;

  public function __construct($uri = '', $kwargs = array()) {
    foreach (parse_url($uri) as $name => $value) $this->$name = $value;
    $this->debug = isset($kwargs['debug']) ? !!$kwargs['debug'] : NULL;
    $this->curlopts = isset($kwargs['curlopts']) ? $kwargs['curlopts'] : array();
  }

  public function __call($name, $args) {
    list($res, $req_headers, $req_body) = $args + array(0, array(), '');

    $opts = $this->curlopts + array(
      CURLOPT_URL => "$this->scheme://$this->host$res",
      CURLOPT_HEADER => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_INFILESIZE => -1,
      CURLOPT_POSTFIELDS => NULL,
      CURLOPT_TIMEOUT => 60,
    );

    foreach ($req_headers as $k => $v) $opts[CURLOPT_HTTPHEADER][] = "$k: $v";
    if ($this->port) $opts[CURLOPT_PORT] = $this->port;
    if ($this->debug) $opts[CURLINFO_HEADER_OUT] = TRUE;
    if ($this->user && $this->pass) $opts[CURLOPT_USERPWD] = "$this->user:$this->pass";
    switch ($name) {
    case 'get':
      $opts[CURLOPT_HTTPGET] = TRUE;
      break;
    case 'post':
      $opts[CURLOPT_POST] = TRUE;
      $opts[CURLOPT_POSTFIELDS] = $req_body;
      break;
    case 'put':
      $opts[CURLOPT_PUT] = TRUE;
      if (strlen($req_body)) {
        if ($buf = fopen('php://memory', 'w+')) {
          fwrite($buf, $req_body);
          fseek($buf, 0);
          $opts[CURLOPT_INFILE] = $buf;
          $opts[CURLOPT_INFILESIZE] = strlen($req_body);
        } else throw new Services_Twilio_TinyHttpException('unable to open temporary file');
      }
      break;
    case 'head':
      $opts[CURLOPT_NOBODY] = TRUE;
      break;
    default:
      $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($name);
      break;
    }
    try {
      if ($curl = curl_init()) {
        if (curl_setopt_array($curl, $opts)) {
          if ($response = curl_exec($curl)) {
            $parts = explode("\r\n\r\n", $response, 3);
            list($head, $body) = ($parts[0] == 'HTTP/1.1 100 Continue')
              ? array($parts[1], $parts[2])
              : array($parts[0], $parts[1]);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($this->debug) {
              error_log(
                curl_getinfo($curl, CURLINFO_HEADER_OUT) .
                $req_body
              );
            }
            $header_lines = explode("\r\n", $head);
            array_shift($header_lines);
            foreach ($header_lines as $line) {
              list($key, $value) = explode(":", $line, 2);
              $headers[$key] = trim($value);
            }
            curl_close($curl);
            if (isset($buf) && is_resource($buf)) fclose($buf);
            return array($status, $headers, $body);
          } else throw new Services_Twilio_TinyHttpException(curl_error($curl));
        } else throw new Services_Twilio_TinyHttpException(curl_error($curl));
      } else throw new Services_Twilio_TinyHttpException('unable to initialize cURL');
    } catch (ErrorException $e) {
      if (is_resource($curl)) curl_close($curl);
      if (isset($buf) && is_resource($buf)) fclose($buf);
      throw $e;
    }
  }

  public function authenticate($user, $pass) {
    $this->user = $user;
    $this->pass = $pass;
  }
}

abstract class Services_Twilio_ListResource
    extends Services_Twilio_Resource
    implements IteratorAggregate
{
    private $_page;

    /**
     * Gets a resource from this list.
     *
     * @param string $sid The resource SID
     * @return Services_Twilio_InstanceResource The resource
     */
    public function get($sid)
    {
        $schema = $this->getSchema();
        $type = $schema['instance'];
        return new $type(is_object($sid)
            ? new Services_Twilio_CachingDataProxy(
                isset($sid->sid) ? $sid->sid : NULL, $this, $sid
            ) : new Services_Twilio_CachingDataProxy($sid, $this));
    }

    /**
     * Deletes a resource from this list.
     *
     * @param string $sid The resource SID
     * @return null
     */
    public function delete($sid, array $params = array())
    {
        $schema = $this->getSchema();
        $basename = $schema['basename'];
        $this->proxy->deleteData("$basename/$sid", $params);
    }

    /**
     * Create a resource on the list and then return its representation as an
     * InstanceResource.
     *
     * @param array $params The parameters with which to create the resource
     *
     * @return Services_Twilio_InstanceResource The created resource
     */
    protected function _create(array $params)
    {
        $schema = $this->getSchema();
        $basename = $schema['basename'];
        return $this->get($this->proxy->createData($basename, $params));
    }

    /**
     * Create a resource on the list and then return its representation as an
     * InstanceResource.
     *
     * @param array $params The parameters with which to create the resource
     *
     * @return Services_Twilio_InstanceResource The created resource
     */
    public function retrieveData($sid, array $params = array())
    {
        $schema = $this->getSchema();
        $basename = $schema['basename'];
        return $this->proxy->retrieveData("$basename/$sid", $params);
    }

    /**
     * Create a resource on the list and then return its representation as an
     * InstanceResource.
     *
     * @param array $params The parameters with which to create the resource
     *
     * @return Services_Twilio_InstanceResource The created resource
     */
    public function createData($sid, array $params = array())
    {
        $schema = $this->getSchema();
        $basename = $schema['basename'];
        return $this->proxy->createData("$basename/$sid", $params);
    }

    /**
     * Returns a page of InstanceResources from this list.
     *
     * @param int   $page The start page
     * @param int   $size Number of items per page
     * @param array $size Optional filters
     *
     * @return Services_Twilio_Page A page
     */
    public function getPage($page = 0, $size = 50, array $filters = array())
    {
        $schema = $this->getSchema();
        $page = $this->proxy->retrieveData($schema['basename'], array(
            'Page' => $page,
            'PageSize' => $size,
        ) + $filters);

        $page->{$schema['list']} = array_map(
            array($this, 'get'),
            $page->{$schema['list']}
        );

        return new Services_Twilio_Page($page, $schema['list']);
    }

    /**
     * Returns meta data about this list resource type.
     *
     * @return array Meta data
     */
    public function getSchema()
    {
        $name = get_class($this);
        $parts = explode('_', $name);
        $basename = end($parts);
        return array(
            'name' => $name,
            'basename' => $basename,
            'instance' => substr($name, 0, -1),
            'list' => self::decamelize($basename),
        );
    }

    public function getIterator($page = 0, $size = 50, array $filters = array())
    {
        return new Services_Twilio_AutoPagingIterator(
            array($this, 'getPageGenerator'),
            create_function('$page, $size, $filters',
                'return array($page + 1, $size, $filters);'),
            array($page, $size, $filters)
        );
    }

    public function getPageGenerator($page, $size, array $filters = array()) {
        return $this->getPage($page, $size, $filters)->getItems();
    }
}

abstract class Services_Twilio_InstanceResource
    extends Services_Twilio_Resource
{
    /**
     * @param mixed $params An array of updates, or a property name
     * @param mixed $value  A value with which to update the resource
     *
     * @return null
     */
    public function update($params, $value = null)
    {
        if (!is_array($params)) {
            $params = array($params => $value);
        }
        $this->proxy->updateData($params);
    }

    /**
     * Set this resource's proxy.
     *
     * @param Services_Twilio_DataProxy $proxy An instance of DataProxy
     *
     * @return null
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Get the value of a property on this resource.
     *
     * @param string $key The property name
     *
     * @return mixed Could be anything.
     */
    public function __get($key)
    {
        if ($subresource = $this->getSubresources($key)) {
            return $subresource;
        }
        return $this->proxy->$key;
    }
}

class Services_Twilio_CachingDataProxy
    implements Services_Twilio_DataProxy
{
    /**
     * The proxy being wrapped.
     *
     * @var DataProxy $proxy
     */
    protected $proxy;

    /**
     * The principal data used to retrieve an object from the proxy.
     *
     * @var array $principal
     */
    protected $principal;

    /**
     * The object cache.
     *
     * @var object $cache
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param array                     $principal Usually the SID
     * @param Services_Twilio_DataProxy $proxy     The proxy
     * @param object|null               $cache     The cache
     */
    public function __construct($principal, Services_Twilio_DataProxy $proxy,
        $cache = null
    ) {
        if (is_scalar($principal)) {
            $principal = array('sid' => $principal, 'params' => array());
        }
        $this->principal = $principal;
        $this->proxy = $proxy;
        $this->cache = $cache;
    }

    /**
     * Set the object cache.
     *
     * @param object $object The new object
     *
     * @return null
     */
    public function setCache($object)
    {
        $this->cache = $object;
    }

    /**
     * Implementation of magic method __get.
     *
     * @param string $prop The name of the property to get
     *
     * @return mixed The value of the property
     */
    public function __get($prop)
    {
        if ($prop == 'sid') {
            return $this->principal['sid'];
        }
        if (empty($this->cache)) {
            $this->_load();
        }
        return isset($this->cache->$prop)
            ? $this->cache->$prop
            : null;
    }

    /**
     * Implementation of retrieveData.
     *
     * @param string $path   The path
     * @param array  $params Optional parameters
     *
     * @return object Object representation
     */
    public function retrieveData($path, array $params = array())
    {
        return $this->proxy->retrieveData(
            $this->principal['sid'] . "/$path",
            $params
        );
    }

    /**
     * Implementation of createData.
     *
     * @param string $path   The path
     * @param array  $params Optional parameters
     *
     * @return object Object representation
     */
    public function createData($path, array $params = array())
    {
        return $this->proxy->createData(
            $this->principal['sid'] . "/$path",
            $params
        );
    }

    /**
     * Implementation of updateData.
     *
     * @param array $params Update parameters
     *
     * @return object Object representation
     */
    public function updateData($params)
    {
        $this->cache = $this->proxy->createData(
            $this->principal['sid'],
            $params
        );
        return $this;
    }

    /**
     * Implementation of deleteData.
     *
     * @param string $path   The path
     * @param array  $params Optional parameters
     *
     * @return null
     */
    public function deleteData($path, array $params = array())
    {
        $this->proxy->delete(
            $this->principal['sid'] . "/$path",
            $params
        );
    }

    /**
     * Retrieves object from proxy into cache, then initializes subresources.
     *
     * @param object|null $object The object
     *
     * @return null
     */
    private function _load($object = null)
    {
        $this->cache = $object !== null
            ? $object
            : $this->proxy->retrieveData($this->principal['sid']);
    }
}

class Services_Twilio_Rest_Accounts
    extends Services_Twilio_ListResource
{
    public function create(array $params = array())
    {
        return parent::_create($params);
    }
}

class Services_Twilio_Rest_Account
    extends Services_Twilio_InstanceResource
{
    protected function init()
    {
        $this->setupSubresources(
            'applications',
            'available_phone_numbers',
            'outgoing_caller_ids',
            'calls',
            'conferences',
            'incoming_phone_numbers',
            'notifications',
            'outgoing_callerids',
            'recordings',
            'sms_messages',
            'transcriptions'
        );

        $this->sandbox = new Services_Twilio_Rest_Sandbox(
            new Services_Twilio_CachingDataProxy('Sandbox', $this)
        );
    }
}

class Services_Twilio_Rest_Applications
    extends Services_Twilio_ListResource
{
    public function create($name, array $params = array())
    {
        return parent::_create(array(
            'FriendlyName' => $name
        ) + $params);
    }
}

class Services_Twilio_Rest_AvailablePhoneNumbers
    extends Services_Twilio_ListResource
{
    public function getLocal($country)
    {
        $curried = new Services_Twilio_PartialApplicationHelper();
        $curried->set(
            'getList',
            array($this, 'getList'),
            array($country, 'Local')
        );
        return $curried;
    }
    public function getTollFree($country)
    {
        $curried = new Services_Twilio_PartialApplicationHelper();
        $curried->set(
            'getList',
            array($this, 'getList'),
            array($country, 'TollFree')
        );
        return $curried;
    }
    public function getList($country, $type, array $params = array())
    {
        return $this->retrieveData("$country/$type", $params);
    }
}

class Services_Twilio_Rest_OutgoingCallerIds
    extends Services_Twilio_ListResource
{
    public function create($phoneNumber, array $params = array())
    {
        return parent::_create(array(
            'PhoneNumber' => $phoneNumber,
        ) + $params);
    }
}

class Services_Twilio_Rest_Calls
    extends Services_Twilio_ListResource
{

    public static function isApplicationSid($value)
    {
        return strlen($value) == 34
            && !(strpos($value, "AP") === false);
    }

    public function create($from, $to, $url, array $params = array())
    {

        $params["To"] = $to;
        $params["From"] = $from;

        if (self::isApplicationSid($url))
            $params["ApplicationSid"] = $url;
        else
            $params["Url"] = $url;

        return parent::_create($params);
    }
}

class Services_Twilio_Rest_Conferences
    extends Services_Twilio_ListResource
{
}

class Services_Twilio_Rest_IncomingPhoneNumbers
    extends Services_Twilio_ListResource
{
    function create(array $params = array())
    {
        return parent::_create($params);
    }
}

class Services_Twilio_Rest_Notifications
    extends Services_Twilio_ListResource
{
}

class Services_Twilio_Rest_Recordings
    extends Services_Twilio_ListResource
{
}

class Services_Twilio_Rest_SmsMessage
    extends Services_Twilio_InstanceResource
{
}

class Services_Twilio_Rest_SmsMessages
    extends Services_Twilio_ListResource
{
    public function getSchema()
    {
        return array(
            'class' => 'Services_Twilio_Rest_SmsMessages',
            'basename' => 'SMS/Messages',
            'instance' => 'Services_Twilio_Rest_SmsMessage',
            'list' => 'sms_messages',
        );
    }

    function create($from, $to, $body, array $params = array())
    {
        return parent::_create(array(
            'From' => $from,
            'To' => $to,
            'Body' => $body
        ) + $params);
    }
}

class Services_Twilio_Rest_Transcriptions
    extends Services_Twilio_ListResource
{
}

class Services_Twilio_Rest_Sandbox
    extends Services_Twilio_InstanceResource
{
}
/* function Services_Twilio_autoload($className) {
    if (substr($className, 0, 15) != 'Services_Twilio') {
        return false;
    }
    $file = str_replace('_', '/', $className);
    $file = str_replace('Services/', '', $file);
	//echo dirname(__FILE__) . "/$file.php" . "<br />";
    return include dirname(__FILE__) . "/$file.php";
}

spl_autoload_register('Services_Twilio_autoload'); */
/**
 * Twilio API client interface.
 *
 * @category Services
 * @package  Services_Twilio
 * @author   Neuman Vong <neuman@twilio.com>
 * @license  http://creativecommons.org/licenses/MIT/ MIT
 * @link     http://pear.php.net/package/Services_Twilio
 */
class Services_Twilio extends Services_Twilio_Resource
{
    const USER_AGENT = 'twilio-php/3.2.2';

    protected $http;
    protected $version;

    /**
     * Constructor.
     *
     * @param string               $sid      Account SID
     * @param string               $token    Account auth token
     * @param string               $version  API version
     * @param Services_Twilio_Http $_http    A HTTP client
     */
    public function __construct(
        $sid,
        $token,
        $version = '2010-04-01',
        Services_Twilio_TinyHttp $_http = null
    ) {
        $this->version = $version;
        if (null === $_http) {
            $_http = new Services_Twilio_TinyHttp(
                "https://api.twilio.com",
                array("curlopts" => array(CURLOPT_USERAGENT => self::USER_AGENT))
            );
        }
        $_http->authenticate($sid, $token);
        $this->http = $_http;
        $this->accounts = new Services_Twilio_Rest_Accounts($this);
        $this->account = $this->accounts->get($sid);
		
		/* $obj = new ErrorLogToConsol();
		$obj->console_log(print_r($this->account)); */
    }

    /**
     * GET the resource at the specified path.
     *
     * @param string $path   Path to the resource
     * @param array  $params Query string parameters
     *
     * @return object The object representation of the resource
     */
    public function retrieveData($path, array $params = array())
    {
        $path = "/$this->version/$path.json";
        return empty($params)
            ? $this->_processResponse($this->http->get($path))
            : $this->_processResponse(
                $this->http->get("$path?" . http_build_query($params, '', '&'))
            );
    }

    /**
     * DELETE the resource at the specified path.
     *
     * @param string $path   Path to the resource
     * @param array  $params Query string parameters
     *
     * @return object The object representation of the resource
     */
    public function deleteData($path, array $params = array())
    {
        $path = "/$this->version/$path.json";
        return empty($params)
            ? $this->_processResponse($this->http->delete($path))
            : $this->_processResponse(
                $this->http->delete("$path?" . http_build_query($params, '', '&'))
            );
    }

    /**
     * POST to the resource at the specified path.
     *
     * @param string $path   Path to the resource
     * @param array  $params Query string parameters
     *
     * @return object The object representation of the resource
     */
    public function createData($path, array $params = array())
    {
        $path = "/$this->version/$path.json";
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        return empty($params)
            ? $this->_processResponse($this->http->post($path, $headers))
            : $this->_processResponse(
                $this->http->post(
                    $path,
                    $headers,
                    http_build_query($params, '', '&')
                )
            );
    }

    /**
     * Convert the JSON encoded resource into a PHP object.
     *
     * @param array $response 3-tuple containing status, headers, and body
     *
     * @return object PHP object decoded from JSON
     */
    private function _processResponse($response)
    {
        list($status, $headers, $body) = $response;
        if ($status == 204) {
            return TRUE;
        }
        if (empty($headers['Content-Type'])) {
            throw new DomainException('Response header is missing Content-Type');
        }
        switch ($headers['Content-Type']) {
        case 'application/json':
            return $this->_processJsonResponse($status, $headers, $body);
            break;
        case 'text/xml':
            return $this->_processXmlResponse($status, $headers, $body);
            break;
        }
        throw new DomainException(
            'Unexpected content type: ' . $headers['Content-Type']);
    }

    private function _processJsonResponse($status, $headers, $body) {
		$global = new Global_Web_Service();
        $decoded = json_decode($body);
		//echo $status;
        if (200 <= $status && $status < 300) {
			echo "IfSuccess: True, ";
            return $decoded;
        } else echo "IfSuccess: False, ";
    }

    private function _processXmlResponse($status, $headers, $body) {
        $decoded = simplexml_load_string($body);
        throw new Services_Twilio_RestException(
            (int)$decoded->Status,
            (string)$decoded->Message,
            (string)$decoded->Code,
            (string)$decoded->MoreInfo
        );
    }
}
