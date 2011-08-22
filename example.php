<?php
// Include PowerSpawn
require_once('PowerSpawn.class.php');

// Initialize the object
$ps = new PowerSpawn;

// Create a callback function (Not required)
function allChildrenComplete() {
	echo "All the children processes have completed\n";
}

function killedChild() {
	echo "A child was just killed\n";
}

// Setup PowerSpawn Config
$ps->setCallback('allChildrenComplete');	// Calls the allChildrenComplete() function when all processes have checked in and there is no more work to complete
$ps->setKillCallback('killedChild');		// Calls the killedChild function when a child is executed for exceeding time limit
$ps->maxChildren = 5;				// Only allows 5 children to be running at any given time
$ps->timeLimit = 10;				// Kills children processes that run longer than 10 seconds

// Build an array with some fake data to proccess
$data = array();
for ($i = 0; $i < 15; $i++) {
	$data[] = $i;
}

// Start the parent loop
while ($ps->runParentCode()) {
	// Only the parent process will run code inside this loop
	
	// Determine if we still have data to process
	if (count($data)) {
		// We still have data to process
		// Check to see if we have an open slot to spawn a child
		if ($ps->spawnReady()) {
			// We can spawn a new process
			$ps->childData = array_shift($data);
			
			// Echo some text to let the user know that this is the parent and we are spawning a new child
			echo "[PARENT] Spawning a new process\n";
			
			// Spawn the process
			$ps->spawnChild();
		} else {
			// All slots are full, just tick for now
			$ps->Tick();
		}
	} else {
		// There is no more data to process
		
		// Echo some text to let the user know that the parent process is out of data
		echo "[PARENT] Out of data to hand out - Waiting for all children to check in\n";
		
		// Call the shutdown() method - blocks until all chilren have checked in or been killed because of executing too long
		$ps->shutdown();
		
		// All child processes have terminated
		echo "[PARENT] All child processes have completed\n";
	}
}

// Start the children code section
if ($ps->runChildCode()) {
	// Only child processes forked by the parent will run this code
	// The parent process will not execute this code
	
	// Get the data for this run
	$myNumber = $ps->childData;
	
	// Let the user know the thread is running
	echo "[CHILD:{$myNumber}] Running with number {$myNumber} - My PID is {$ps->myPID()}\n";
	
	// Now we'll sleep x seconds where x is $myNumber - but we'll loop to show that we are doing work
	$x = 0;
	while ($x < $myNumber) {
		sleep(1);
		$x++;
		echo "[CHILD:{$myNumber}] Just completed iteration {$x} of {$myNumber}\n";
	}

	// Let the user know that all work has been completed
	// All children that run longer than 10 seconds won't make it this far with the settings above
	echo "[CHILD:{$myNumber}] I have completed my work\n";
}


// Send signal 0 to let parent process know we are done
exit(0);

?>
