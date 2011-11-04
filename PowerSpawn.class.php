<?php
/*
 * PowerSpawn
 *
 * Object wrapper for handling process forking within PHP
 * Depends on PCNTL package
 * Depends on POSIX package
 *
 * Author:	Don Bauer
 * E-Mail:	lordgnu@me.com
 *
 * Date:	2011-11-04
 */

declare(ticks = 1);

class PowerSpawn {
	private	$myChildren;
	private	$parentPID;
	private	$shutdownCallback = null;
	private $killCallback = null;

	public	$maxChildren	=	10;		// Max number of children allowed to Spawn
	public	$timeLimit		=	0;		// Time limit in seconds (0 to disable)
	public	$sleepCount	=	100;		// Number of uSeconds to sleep on Tick()

	public	$childData;					// Variable for storage of data to be passed to the next spawned child
	public	$complete;

	public function __construct() {
		if (function_exists('pcntl_fork') && function_exists('posix_getpid')) {
			// Everything is good
			$this->parentPID = $this->myPID();
			$this->myChildren = array();
			$this->complete = false;

			// Install the signal handler
			pcntl_signal(SIGCHLD, array($this, 'sigHandler'));
		} else {
			die("You must have POSIX and PCNTL functions to use PowerSpawn\n");
		}
	}

	public function __destruct() {

	}

	public function sigHandler($signo) {
		switch ($signo) {
			case SIGCHLD:
				$this->checkChildren();
				break;
		}
	}
	
	public function getChildStatus($name = false) {
		if ($name === false) return false;
		if (isset($this->myChildren[$name])) {
			return $this->myChildren[$name];
		} else {
			return false;
		}
	}

	public function checkChildren() {
		foreach ($this->myChildren as $i => $child) {
			// Check for time running and if still running
			if ($this->pidDead($child['pid']) != 0) {
				// Child is dead
				unset($this->myChildren[$i]);
			} elseif ($this->timeLimit > 0) {
				// Check the time limit
				if (time() - $child['time'] >= $this->timeLimit) {
					// Child had exceeded time limit
					$this->killChild($child['pid']);
					unset($this->myChildren[$i]);
				}
			}
		}
	}

	public function myPID() {
		return posix_getpid();
	}

	public function myParent() {
		return posix_getppid();
	}

	public function spawnChild($name = false) {
		$time = time();
		$pid = pcntl_fork();
		if ($pid) {
			if ($name !== false) {
				$this->myChildren[$name] = array('time'=>$time,'pid'=>$pid);
			} else {
				$this->myChildren[] = array('time'=>$time,'pid'=>$pid);
			}
		}
	}

	public function killChild($pid = 0) {
		if ($pid > 0) {
			posix_kill($pid, SIGTERM);
			if ($this->killCallback !== null) call_user_func($this->killCallback);
		}
	}

	public function parentCheck() {
		if ($this->myPID() == $this->parentPID) {
			return true;
		} else {
			return false;
		}
	}

	public function pidDead($pid = 0) {
		if ($pid > 0) {
			return pcntl_waitpid($pid, $status, WUNTRACED OR WNOHANG);
		} else {
			return 0;
		}
	}

	public function setCallback($callback = null) {
		$this->shutdownCallback = $callback;
	}
	
	public function setKillCallback($callback = null) {
		$this->killCallback = $callback;
	}

	public function childCount() {
		return count($this->myChildren);
	}

	public function runParentCode() {
		if (!$this->complete) {
			return $this->parentCheck();
		} else {
			if ($this->shutdownCallback !== null)
				call_user_func($this->shutdownCallback);
			return false;
		}
	}

	public function runChildCode() {
		return !$this->parentCheck();
	}

	public function spawnReady() {
		if (count($this->myChildren) < $this->maxChildren) {
			return true;
		} else {
			return false;
		}
	}

	public function shutdown() {
		while($this->childCount()) {
			$this->checkChildren();
			$this->tick();
		}
		$this->complete = true;
	}

	public function tick() {
		usleep($this->sleepCount);
	}
	
	public function exec($proc, $args = null) {
		if ($args == null) {
			pcntl_exec($proc);
		} else {
			pcntl_exec($proc, $args);
		}
	}
}

?>
