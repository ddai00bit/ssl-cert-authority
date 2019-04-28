<?php
class LDAP {
	private $conn;
	private $host;
	private $starttls;
	private $bind_dn;
	private $bind_password;
	private $options;

	public function __construct($host, $starttls, $bind_dn, $bind_password, $options) {
		$this->conn = null;
		$this->host = $host;
		$this->starttls = $starttls;
		$this->bind_dn = $bind_dn;
		$this->bind_password = $bind_password;
		$this->options = $options;
	}

	private function connect() {
		$this->conn = ldap_connect($this->host);
		if($this->conn === false) throw new LDAPConnectionFailureException('Invalid LDAP connection settings');
		if($this->starttls) {
			if(!ldap_start_tls($this->conn)) throw new LDAPConnectionFailureException('Could not initiate TLS connection to LDAP server');
		}
		foreach($this->options as $option => $value) {
			ldap_set_option($this->conn, $option, $value);
		}
		if(!empty($this->bind_dn)) {
			if(!ldap_bind($this->conn, $this->bind_dn, $this->bind_password)) throw new LDAPConnectionFailureException('Could not bind to LDAP server');
		}
	}

	public function search($basedn, $filter, $fields = array(), $sort = array()) {
		if(is_null($this->conn)) $this->connect();
		if(empty($fields)) $r = @ldap_search($this->conn, $basedn, $filter);
		else $r = @ldap_search($this->conn, $basedn, $filter, $fields);
		$sort = array_reverse($sort);
		foreach($sort as $field) {
			@ldap_sort($this->conn, $r, $field);
		}
		if($r) {
			// Fetch entries
			$result = @ldap_get_entries($this->conn, $r);
			unset($result['count']);
			$items = array();
			foreach($result as $item) {
				unset($item['count']);
				$itemResult = array();
				foreach($item as $key => $values) {
					if(!is_int($key)) {
						if(is_array($values)) {
							unset($values['count']);
							if(count($values) == 1) $values = $values[0];
						}
						$itemResult[$key] = $values;
					}
				}
				$items[] = $itemResult;
			}
			return $items;
		}
		return false;
	}

	public static function escape($str = '') {
		$metaChars = array("\\00", "\\", "(", ")", "*");
		$quotedMetaChars = array();
		foreach($metaChars as $key => $value) {
			$quotedMetaChars[$key] = '\\'. dechex(ord($value));
		}
		$str = str_replace($metaChars, $quotedMetaChars, $str);
		return $str;
	}
}

class LDAPConnectionFailureException extends RuntimeException {}
