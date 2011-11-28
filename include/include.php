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
define('MEMBASE_PORT', 11211);
define('COUCHBASE_PORT',5984);
define('COUCHBASE_ADMIN_PORT',8091);
define('APP_PREFIX',"chat");
define('KEY_DELIM',"::");

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
		self::$cb_obj->addCouchbaseServer(COUCHBASE_SERVER,MEMBASE_PORT,COUCHBASE_PORT,COUCHBASE_SERVER,COUCHBASE_ADMIN_PORT);
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
		$view = $cb_obj->getView("chats","get_users");
		$result = $view->getResultByKey($userid);
		if(count($result->rows) == 0){
	      $user_doc = new stdClass;
         $user_doc->type = "user";
         $user_doc->userid = $userid;
      	$user_doc->sha1password = sha1($password);
      	$json_doc = json_encode($user_doc);
      	$res = $cb_obj->couchdb->saveDoc($json_doc);
		} else {
			$this -> last_error_string = "User id '<i>$userid</i>' exists, please choose another.";
			return false;
		}
		return(true);
	}

	/**
	 * Attempt to login the user
	 * @param string $userid
	 * @param string $password
	 * @return boolean
	 */
	public function loginUser($userid, $password) {
		//check to sees if userid exists with the same password hashed
      $cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
      $view = $cb_obj->getView("chats","get_users");
      $result = $view->getResultByKey($userid);
		if(count($result->rows)==1){
			if($result->rows[0]->value->sha1password == sha1($password)){
				$_SESSION{"userid"} = $userid;
				return(true);
			}
		}
		return(false);
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
		#get the actual total number of comments, including deleted ones
      $comment_count_key = APP_PREFIX . KEY_DELIM . "chatcount";
      $comment_count = $cb_obj -> get($comment_count_key);
		$view = $cb_obj->getView("chats","get_chats");
		#use the Paginator to retrieve only (at most) $count comments
		#from the most recent (highest comment number) counting down
		$result_pages = $view->getResultPaginator();
		$result_pages->setRowsPerPage($count);
      $result_pages->setOptions(array("descending" => true));
		$first_page = $result_pages->current();

		#populate an associative array with keys numbered from the max
		#comment number, counting down by $count
		$comment_array = array();
		for($i=$comment_count;$i>0 && $i>$comment_count-$count;$i--){
			$comment_key = APP_PREFIX . KEY_DELIM . "COMMENT#" . $i;
			$comment_array[$comment_key]=null;
		}
		#now fill the associative arraywith the keys that haven't been deleted
		for($i=0;$i<count($first_page->rows) && $i<$count ;$i++){
			$this_record = $first_page->rows[$i];
			$comment_key = APP_PREFIX . KEY_DELIM . "COMMENT#" . $this_record->key;
			if(array_key_exists($comment_key,$comment_array)){
				$comment_value = $this_record->value->userid . KEY_DELIM . $this_record->value->chat;
				$comment_array[$comment_key] = $comment_value;
			}
		}
		return $comment_array;
	}

	/**
	 * Add a comment to the comment list
	 * @param string
	 */
	public function addComment($comment) {
		if($comment != "") {
      	$cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
			#get and increment the comment count index via the Membase
			#API inherited by couchbase
         $comment_count_key = APP_PREFIX . KEY_DELIM . "chatcount";
         $comment_count = $cb_obj -> get($comment_count_key);
         if($cb_obj -> getResultCode() == Memcached::RES_NOTFOUND) {
            $cb_obj -> add($comment_count_key, 0);
			}
         $script_comment_count = $cb_obj -> increment($comment_count_key);
			#create the chat object to save in the Coucbase record
         $chat_doc = new stdClass;
         $chat_doc->type = "chat";
			$chat_doc->commentnum = $script_comment_count;
         $chat_doc->userid = $_SESSION{"userid"};
			$chat_doc->chat = $comment;
			#encode and save the chat object
         $json_doc = json_encode($chat_doc);
         $res = $cb_obj->couchdb->saveDoc($json_doc);
		}
	}

	/**
	 * delete a comment from the list by number
	 * @param integer $number
	 */
	public function deleteComment($number) {
      $cb_obj = CouchbaseSingleton::getInstance() -> getCouchbase();
		#retrieve the comment number to be deleted
		$view = $cb_obj->getView("chats","get_chats");
      $result = $view->getResultByKey(intval($number));
      if(count($result->rows)==1){
			#use the Membase api inherited by the Couchbase API to 
			#delete the underlying Membase record by the id
         $comment_count = $cb_obj -> delete($result->rows[0]->id);
			#deletion is not synchronous so we give it a chance
			#to catch up here by waiting for one second as we 
			#will immediately be retrieving comments to show the list
			sleep(1);
			return(true);
      }
      return(false);
	}
}
?>
