<?php

/**
 * Use a Memcached key. The value will be the PID and Memcached ttl will be used to implement lock expiration.
 * 
 * @author Shane Harter
 * @since 2011-07-28
 */
class Core_Lock_Memcached extends Core_Lock_Lock implements Core_IPlugin
{
    /**
     * @var Core_Memcache
     */
	private $memcache = false;

    /**
     * @var array
     */
	public $memcache_servers = array();
	
	public function setup()
	{
		// Connect to memcache
		$this->memcache = new Core_Lib_Memcache();
		$this->memcache->ns($this->daemon_name);
		
		// We want to use the auto-retry feature built into our memcache wrapper. This will ensure that the occasional blocking operation on
		// the memcache server doesn't crash the daemon. It'll retry every 1/10 of a second until it hits its limit. We're giving it a 1 second limit.
		$this->memcache->auto_retry(1);
		
		if ($this->memcache->connect_all($this->memcache_servers) === false)
			throw new Exception('Core_Lock_Memcached::setup failed: Memcached Connection Failed');
	}
	
	public function teardown()
	{
		// If this PID set this lock, release it
		if ($this->get() == $this->pid)
			$this->memcache->delete(Core_Lock_Lock::$LOCK_UNIQUE_ID);
	}
	
	public function check_environment(Array $errors = array())
	{
		$errors = array();
		
		if (!(is_array($this->memcache_servers) && count($this->memcache_servers)))
			$errors[] = 'Memcache Plugin: Memcache Servers Are Not Set';
		
        if (!class_exists('Memcached'))
            $errors[] = 'Memcache Plugin: PHP Memcached Extension Is Not Loaded';
        	
		if (!class_exists('Core_Lib_Memcache'))
			$errors[] = 'Memcache Plugin: Dependant Class "Core_Lib_Memcache" Is Not Loaded';

		return $errors;
	}
	
	protected function set()
	{
		$this->memcache->set(Core_Lock_Lock::$LOCK_UNIQUE_ID, $this->pid);
	}
	
	protected function get()
	{
		$lock = $this->memcache->get(Core_Lock_Lock::$LOCK_UNIQUE_ID);
		
		return $lock;
	}
}