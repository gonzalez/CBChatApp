<?php
/**
 *  This is contains common files for the WebChat example
 *  application. It includes a variety of global constants
 *  and the User and Comment classes which
 *  This is the Couchbase version which maintains it's state
 *  in the Couchbase database
 *
 */
require_once("Couchbase.php");

//comprehensive debug output for development server
//we would remove this on a production server
error_reporting(E_ALL);
ini_set("display_errors", 1);

//start our session
session_start();

//Global constants
$APP_NAME = "PHP Couchbase WebChat";
define('COUCHBASE_SERVER', "localhost");
define('APP_PREFIX',"chat");
define('KEY_DELIM',"::");
define('MEMBASE_SERVER',"localhost");
define('MEMBASE_PORT',11211);

#$csi = CouchbaseSingleton::getInstance();
#$csi->getCouchbase()->flush();
#exit;

/**
 * Singleton Couchbase class
 * This keeps a single global copy of the Couchbase
 * connection
 */
class CouchbaseSingleton {
	private static $instance;
	private static $cb_obj;

	/**
	 * Construct the object
	 */
	private function __construct() {
	}

	/**
	 * Initialize this class after construction
	 */
	private function initialize() {
		self::$cb_obj = new Couchbase();
		self::$cb_obj->setOption(Memcached::OPT_COMPRESSION,false);
        	self::$cb_obj->addServer(MEMBASE_SERVER, MEMBASE_PORT);
	}

	/**
	 * Return the singleton instance, constructing and
	 * and initializing it if it doesn't already exist
	 */
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new self();
			self::$instance -> initialize();
		}
		return self::$instance;
	}

	/**
	 * Return the Memcached object held by the singleton
	 */
	public static function getCouchbase() {
		return (self::$cb_obj);
	}
}

/**
 * User class
 * This handles user interactions with Membase through
 * the Memcached interface.
 */
class User {
	private $last_error_string;
	/**
	 * Create a user account based on provided userid
	 * and password.
	 * @param string $userid
	 * @param string $password
	 * @return boolean
	 */
	public function createUserAccount($userid, $password) {
		$error = "";
		if(!preg_match("/^\w{4,10}$/", $userid)) {
			$error .= "Illegal userid '<i>$userid</i>'<br>";
		}
		if(!preg_match("/^.{4,10}/", $password)) {
			$error .= "Password must have between 4 and 10 characters";
		}
		if($error != "") {
			$this -> last_error_string = $error;
			return false;
		}

		$cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
		//check to see if the userid already exists
		$userid_key = APP_PREFIX . KEY_DELIM . "user" .
KEY_DELIM . $userid;
		$passwordHash = sha1($password);
		if($cb_obj -> add($userid_key, $passwordHash)) {
		//now that we've added the userid key we'll add it to the userlist
			$userlist_key = APP_PREFIX . KEY_DELIM . "userlist";
			if(!$cb_obj -> add($userlist_key, $userid)) {
				$cb_obj -> append($userlist_key, KEY_DELIM . $userid);
			}
			return true;
		} else {
			$result_code = $cb_obj -> getResultCode();
			if($result_code == Memcached::RES_NOTSTORED) {
				$this -> last_error_string .=
				"User id '<i>$userid</i>' exists, please choose another.";
			} else {
				$this -> last_error_string .=
				"Error, please contact administrator:" .
				$cb_obj -> getResultMessage();
			}
			return false;
		}

	}

	/**
	 * Attempt to login the user
	 * @param string $userid
	 * @param string $password
	 * @return boolean
	 */
	public function loginUser($userid, $password) {
		$cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
		//check to sees if userid exists with the same password hashed
		$userid_key = APP_PREFIX . KEY_DELIM .
 "user" . KEY_DELIM . $userid;
		$submitted_passwordHash = sha1($password);
		$db_passwordHash = $cb_obj -> get($userid_key);
		if($db_passwordHash == false) {
			return (false);
		}
		//do we match the password?
		if($db_passwordHash == $submitted_passwordHash) {
			$_SESSION{"userid"} = $userid;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Log the user out
	 */
	public function logoutUser() {
		session_unset();
		session_destroy();
	}

	/**
	 * Get the error string from the last action
	 * @return string
	 */
	public function getLastErrorString() {
		return $this -> last_error_string;
	}

}

/**
 * Comments class for managing comments in Membase
 * through the Memcached library
 */
class Comments {
     /**
      * Return the last n comments
      * @param integer $count
      * @return Array
      */
     public function getLastComments($count) {
         $cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
         $comment_count_key = APP_PREFIX . KEY_DELIM . "chatcount";
         $script_comment_count = $cb_obj -> get($comment_count_key);
         $comment_key_list = array();
         for($i = $script_comment_count; $i > 0 &&
              $i > ($script_comment_count - $count); $i--) {
              array_push($comment_key_list, "COMMENT#" . $i);
         }
         $null = null;
         return ($cb_obj -> getMulti($comment_key_list,
             $null, Memcached::GET_PRESERVE_ORDER));
     }
     /**
      * Add a comment to the comment list
     * @param string
     */
    public function addComment($comment) {
         $cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
         if($comment != "") {
             $comment_count_key = APP_PREFIX . KEY_DELIM . "chatcount";
             $comment_count = $cb_obj -> get($comment_count_key);
             if($cb_obj -> getResultCode() == Memcached::RES_NOTFOUND) {
                 $cb_obj -> add($comment_count_key, 0);
             }
             $script_comment_count = $cb_obj -> increment($comment_count_key);
             $cb_obj -> add("COMMENT#" . $script_comment_count, $_SESSION{"userid"} .
              KEY_DELIM . $_POST["comment"]);
         }
     }
     /**
      * delete a comment from the list by number
      * @param integer $number
      */
     public function deleteComment($number) {
         $cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
         $comment_key = "COMMENT#" . $number;
         $result = $cb_obj -> get($comment_key);
         $elements = explode(KEY_DELIM, $result);
         $userid = array_shift($elements);
         //make sure user who created is deleting
         if($userid == $_SESSION{"userid"}) {
             $result = $cb_obj -> delete($comment_key);
         }
     }
}
?>
