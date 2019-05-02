<?php
chdir(dirname(__FILE__));
mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
set_error_handler('exception_error_handler');
spl_autoload_register('autoload_model');

require('pagesection.php');

$config_file = 'config/config.ini';
if(file_exists($config_file)) {
	$config = parse_ini_file($config_file, true);
} else {
	throw new Exception("Config file $config_file does not exist.");
}

require('router.php');
require('routes.php');
require('ldap.php');
require('email.php');

if ($config['ldap']['enabled'] == 1) {
	$ldap_options = array();
	$ldap_options[LDAP_OPT_PROTOCOL_VERSION] = 3;
	$ldap_options[LDAP_OPT_REFERRALS] = !empty($config['ldap']['follow_referrals']);
	$ldap = new LDAP($config['ldap']['host'], $config['ldap']['starttls'], $config['ldap']['bind_dn'], $config['ldap']['bind_password'], $ldap_options);
}
setup_database();

$relative_frontend_base_url = (string)parse_url($config['web']['baseurl'], PHP_URL_PATH);

// Convert all non-fatal errors into exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline) {
	throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

// Autoload needed model files
function autoload_model($classname) {
	global $base_path;
	$classname = preg_replace('/[^a-z]/', '', strtolower($classname)); # Prevent directory traversal and sanitize name
	$filename = path_join($base_path, 'model', $classname.'.php');
	if(file_exists($filename)) {
		include($filename);
	} else {
		eval("class $classname {}");
		throw new InvalidArgumentException("Attempted to load a class $classname that did not exist.");
	}
}

// Setup database connection and models
function setup_database() {
	global $config, $database, $driver, $user_dir, $server_dir, $event_dir, $sync_request_dir, $certificate_dir, $script_dir, $service_dir, $profile_dir;
	try {
		$database = new mysqli($config['database']['hostname'], $config['database']['username'], $config['database']['password'], $config['database']['database'], $config['database']['port']);
	} catch(ErrorException $e) {
		throw new DBConnectionFailedException($e->getMessage());
	}
	$database->set_charset('utf8mb4');
	$driver = new mysqli_driver();
	$driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
	$migration_dir = new MigrationDirectory;
	$user_dir = new UserDirectory;
	$server_dir = new ServerDirectory;
	$event_dir = new EventDirectory;
	$sync_request_dir = new SyncRequestDirectory;
	$certificate_dir = new CertificateDirectory;
	$script_dir = new ScriptDirectory;
	$service_dir = new ServiceDirectory;
	$profile_dir = new ProfileDirectory;
}

/**
 * Join a sequence of partial paths into a complete path
 * e.g. pathJoin("foo", "bar") -> foo/bar
 *      pathJoin("f/oo", "b/ar") -> f/oo/b/ar
 *      pathJoin("/foo/b/", "ar") -> "/foo/b/ar"
 * @param string part of path
 * @return string joined path
 */
function path_join() {
	$args = func_get_args();
	$parts = array();
	foreach($args as $arg) {
		$parts = array_merge($parts, explode("/", $arg));
	}
	$parts = array_filter($parts, function($x) {return (bool)($x);});
	$prefix = $args[0][0] == "/" ? "/" : "";
	return $prefix . implode("/", $parts);
}

define('ESC_HTML', 1);
define('ESC_URL', 2);
define('ESC_URL_ALL', 3);
define('ESC_NONE', 9);

/**
* Output the given string, HTML-escaped by default
* @param string $string to output
* @param integer $escaping method of escaping to use
*/
function out($string, $escaping = ESC_HTML) {
	switch($escaping) {
	case ESC_HTML:
		echo htmlspecialchars($string);
		break;
	case ESC_URL:
		echo urlencode($string);
		break;
	case ESC_URL_ALL:
		echo rawurlencode($string);
		break;
	case ESC_NONE:
		echo $string;
		break;
	default:
		throw new InvalidArgumentException("Escaping format $escaping not known.");
	}
}

/**
* Generate a root-relative URL from the base URL and the given base-relative URL
* @param string $url base-relative URL
* @return string root-relative URL
*/
function rrurl($url) {
	global $relative_frontend_base_url;
	return $relative_frontend_base_url.$url;
}

/**
* Output a root-relative URL from the base URL and the given base-relative URL
* @param string $url relative URL
*/
function outurl($url) {
	out(rrurl($url));
}

/**
 * Short-name HTML escape convenience function
 * @param string $string string to escape
 * @return string HTML-escaped string
 */
function hesc($string) {
	return htmlspecialchars($string);
}

function english_list($array) {
	if(count($array) == 1) return reset($array);
	else return implode(', ', array_slice($array, 0, -1)).' and '.end($array);
}

/**
 * Perform an HTTP redirect to the given URL (or the current URL if none given)
 * @param string|null $url URL to redirect to
 * @param string $type HTTP response code/name to use
 */
function redirect($url = null, $type = '303 See other') {
	global $absolute_request_url, $relative_frontend_base_url;
	if(is_null($url)) {
		// Redirect is to current URL
		$url = $absolute_request_url;
	} elseif(substr($url, 0, 1) !== '#') {
		$url = $relative_frontend_base_url.$url;
	}
	header("HTTP/1.1 $type");
	header("Location: $url");
	print("\n");
	exit;
}

/**
 * Given a set of defaults and an array of querystring data, convert to a simpler
 * easy-to-read form and redirect if any conversion was done.  Also return array
 * combining defaults with any querysting parameters that do not match defaults.
 * @param array $defaults associative array of default values
 * @param array $values associative array of querystring data
 * @return array result of combining defaults and querystring data
 */
function simplify_search($defaults, $values) {
	global $relative_request_url;
	$simplify = false;
	$simplified = array();
	foreach($defaults as $key => $default) {
		if(!isset($values[$key])) {
			// No value provided, use default
			$values[$key] = $default;
		} elseif(is_array($values[$key])) {
			if($values[$key] == $default) {
				// Parameter not needed in URL if it matches the default
			} else {
				// Simplify array to semicolon-separated string in URL
				$simplified[] = urlencode($key).'='.implode(';', array_map('urlencode', $values[$key]));
			}
			$simplify = true;
		} elseif($values[$key] == $default) {
			// Parameter not needed in URL if it matches the default
			$simplify = true;
		} else {
			// Pass value as-is to simplified array
			$simplified[] = urlencode($key).'='.urlencode($values[$key]);
			if(is_array($default)) {
				// We expect an array; extract array values from semicolon-separated string
				$values[$key] = explode(';', $values[$key]);
			}
		}
	}
	if($simplify) {
		$url = preg_replace('/\?.*$/', '', $relative_request_url);
		if(count($simplified) > 0) $url .= '?'.implode('&', $simplified);
		redirect($url);
	} else {
		return $values;
	}
}

/**
 * Returns the given string in its hexadecimal representation
 * @param string string to convert to hex
 * @return array hex representation of the input string
 */
function to_hex($data) {
    return strtoupper(bin2hex($data));
}

/**
* Decrypt data from a CryptoJS json encoding string
*
* @param mixed $passphrase
* @param mixed $jsonString
* @return mixed
*/
function cryptoJsAesDecrypt($passphrase, $jsonString) {
    $jsondata = json_decode($jsonString, true);
    try {
        $salt = hex2bin($jsondata["s"]);
        $iv  = hex2bin($jsondata["iv"]);
    } catch(Exception $e) { return null; }
    $ct = base64_decode($jsondata["ct"]);
    $concatedPassphrase = $passphrase.$salt;
    $md5 = array();
    $md5[0] = md5($concatedPassphrase, true);
    $result = $md5[0];
    for ($i = 1; $i < 3; $i++) {
        $md5[$i] = md5($md5[$i - 1].$concatedPassphrase, true);
        $result .= $md5[$i];
    }
    $key = substr($result, 0, 32);
    $data = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
    return json_decode($data, true);
}

/**
 * Helper method to query a parameter from $POST or $GET. 
 * Will return 422 if a parameter is not available.
 * @param array $_GET or $_POST array
 * @param string paramter to query
 * @return string value of the parameter
 */
function getParameterOrDie($method, $parameter) {
	if(isset($method[$parameter])) {
		return trim($method[$parameter]);
	}
	require('views/error422.php');
	die;
}

/**
 * Helper method to query a parameter from $POST or $GET. 
 * Will return null if parameter is not set.
 * @param array $_GET or $_POST array
 * @param string paramter to query
 * @return string value of the parameter or null
 */
function getOptParameter($method, $parameter) {
	if(isset($method[$parameter])) {
		return trim($method[$parameter]);
	}
	return null;
}

class OutputFormatter {
	public function comment_format($text) {
		return hesc($text);
	}
}

$output_formatter = new OutputFormatter;

foreach(glob("extensions/*.php") as $filename) {
    include $filename;
}

class DBConnectionFailedException extends RuntimeException {}
