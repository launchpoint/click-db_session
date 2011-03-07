<?




class dbSession
{

    /**
     *  Constructor of class
     *
     *  Initializes the class and starts a new session
     *
     *  There is no need to call start_session() after instantiating this class
     *
     *  @param  integer     $gc_maxlifetime     (optional) the number of seconds after which data will be seen as 'garbage' and
     *                                          cleaned up on the next run of the gc (garbage collection) routine
     *
     *                                          Default is specified in php.ini file
     *
     *  @param  integer     $gc_probability     (optional) used in conjunction with gc_divisor, is used to manage probability that
     *                                          the gc routine is started. the probability is expressed by the formula
     *
     *                                          probability = $gc_probability / $gc_divisor
     *
     *                                          So if $gc_probability is 1 and $gc_divisor is 100 means that there is
     *                                          a 1% chance the the gc routine will be called on each request
     *
     *                                          Default is specified in php.ini file
     *
     *  @param  integer     $gc_divisor         (optional) used in conjunction with gc_probability, is used to manage probability
     *                                          that the gc routine is started. the probability is expressed by the formula
     *
     *                                          probability = $gc_probability / $gc_divisor
     *
     *                                          So if $gc_probability is 1 and $gc_divisor is 100 means that there is
     *                                          a 1% chance the the gc routine will be called on each request
     *
     *                                          Default is specified in php.ini file
     *
     *  @param  string      $securityCode       the value of this argument is appended to the HTTP_USER_AGENT before creating the
     *                                          md5 hash out of it. this way we'll try to prevent HTTP_USER_AGENT spoofing
     *
     *                                          Default is 'sEcUr1tY_c0dE'
     *
     *  @return void
     */
    function dbSession($gc_maxlifetime = "18000", $gc_probability = "", $gc_divisor = "", $securityCode = "sEcUr1tY_c0dE")
    {

        // if $gc_maxlifetime is specified and is an integer number
        if ($gc_maxlifetime != "" && ctype_digit($gc_maxlifetime)) {

            // set the new value
            ini_set('session.gc_maxlifetime', $gc_maxlifetime);

        }
				
					//ini_set('session.gc_maxlifetime', $gc_maxlifetime);

        // if $gc_probability is specified and is an integer number
        if ($gc_probability != "" && ctype_digit($gc_probability)) {

            // set the new value
            @ini_set('session.gc_probability', $gc_probability);

        }

        // if $gc_divisor is specified and is an integer number
        if ($gc_divisor != "" && ctype_digit($gc_divisor)) {

            // set the new value
            @ini_set('session.gc_divisor', $gc_divisor);

        }

        // get session lifetime
        $this->sessionLifetime = ini_get("session.gc_maxlifetime");
        
        // we'll use this later on in order to try to prevent HTTP_USER_AGENT spoofing
        $this->securityCode = $securityCode;

        // register the new handler
        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );
        register_shutdown_function('session_write_close');
     }

    /**
     *  Deletes all data related to the session
     *
     *  @since 1.0.1
     *
     *  @return void
     */
    function stop()
    {
        $this->regenerate_id();

        session_unset();

        session_destroy();

    }

    /**
     *  Regenerates the session id.
     *
     *  <b>Call this method whenever you do a privilege change!</b>
     *
     *  @return void
     */
    function regenerate_id()
    {
	
				global $conn;
				
        // saves the old session's id
        $oldSessionID = session_id();

        // regenerates the id
        // this function will create a new session, with a new id and containing the data from the old session
        // but will not delete the old session
        session_regenerate_id();
				
				// holds the new session id
				$newSessionID = session_id();

        // because the session_regenerate_id() function does not delete the old session,
        // we have to delete it manually
        //$this->destroy($oldSessionID);
				
        // updated the old session with the new session id
        $session = query_assoc("update sessions set id = '$newSessionID' where id = '$oldSessionID'");
    }

    /**
     *  Get the number of online users
     *
     *  @return integer     number of users currently online
     */
    function get_users_online()
    {
				global $conn;

        // call the garbage collector
        $this->gc($this->sessionLifetime);

        // counts the rows from the database
        $res = query_assoc("select count(*) c from sessions");
        return $res[0]['c'];
    }

    /**
     *  Custom open() function
     *
     *  @access private
     */
    function open($save_path, $session_name)
    {

        return true;

    }

    /**
     *  Custom close() function
     *
     *  @access private
     */
    function close()
    {

        return true;

    }

    /**
     *  Custom read() function
     *
     *  @access private
     */
    function read($session_id)
    {
		
				global $conn;

        // reads session data associated with the session id
        // but only
        // - if the HTTP_USER_AGENT is the same as the one who had previously written to this session AND
        // - if session has not expired
        $md5= md5($this->ua() . $this->securityCode);
        $session = query_assoc("select * from sessions where id='$session_id'");
        $this->insert=true;
        if (count($session)==0 || $session[0]['expire'] < time() ) return "";
        $this->insert=false;
        return $session[0]['data'];
    }

    /**
     *  Custom write() function
     *
     *  @access private
     */
    function write($session_id, $session_data)
    {
        // insert OR update session's data - this is how it works:
        // first it tries to insert a new row in the database BUT if session_id is already in the database then just
        // update session_data and session_expire for that specific session_id
        // read more here http://dev.mysql.com/doc/refman/4.1/en/insert-on-duplicate.html
        $md5 = md5($this->ua() . $this->securityCode);
        $session = query_assoc("select * from sessions where id='$session_id'");
        $expire = time() + $this->sessionLifetime;
        $data = mysql_escape_string($session_data);
        if (count($session)==0)
        {
          query("insert into sessions (id, http_user_agent, data, expire) values ('$session_id', '$md5', '$data', $expire)");
        } else {
          query("update sessions set data='$data', expire=$expire where id='$session_id'");
        }
				return true;
    }
    
    function ua()
    {
      if (array_key_exists("HTTP_USER_AGENT", $_SERVER)) return $_SERVER["HTTP_USER_AGENT"];
      return 'bot';
    }
    
    

    /**
     *  Custom destroy() function
     *
     *  @access private
     */
    function destroy($session_id)
    {

				global $conn;
				
				query("delete from sessions where id = '$session_id'");
				
				return true;

    }

    /**
     *  Custom gc() function (garbage collector)
     *
     *  @access private
     */
    function gc($maxlifetime)
    {

				global $conn;

				query("delete from sessions where expire > $maxlifetime");
    }

}