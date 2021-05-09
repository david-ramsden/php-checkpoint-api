<?php
/**
 * Copyright (c) 2021 David Ramsden
 *
 * This software is provided 'as-is', without any express or implied
 * warranty. In no event will the authors be held liable for any damages
 * arising from the use of this software.
 *
 * Permission is granted to anyone to use this software for any purpose,
 * including commercial applications, and to alter it and redistribute it
 * freely, subject to the following restrictions:
 *
 * 1. The origin of this software must not be misrepresented; you must not
 *   claim that you wrote the original software. If you use this software
 *   in a product, an acknowledgment in the product documentation would be
 *   appreciated but is not required.
 * 2. Altered source versions must be plainly marked as such, and must not be
 *   misrepresented as being the original software.
 * 3. This notice may not be removed or altered from any source distribution.
 */

include('CPAPI.class.php');

$cp = new CPAPI(array('server' 		=> '10.10.10.134',
		      		  'user'	 	=> 'svc_cpapi',
		      		  'password' 	=> 'th3_pa55w0rd'));

$packages = $cp->send_request('show-packages', array('limit' => 500, 'offset' => 0));

foreach ($packages->{'packages'} as $package) {
	if ($package->{'type'} === 'package') {
		// Skip certain policies.
		//if ($package->{'name'} === 'Test_Policy') { continue; }

		$package = $cp->send_request('show-package', array('uid' => $package->{'uid'}));

		pline("Policy verify: " . $package->{'name'});

		$result = $cp->send_request('verify-policy', array('policy-package' => $package->{'uid'}));

		list($status, $percent, $details) = get_status($result->{'task-id'});
		display_details($details);

		if ($status === 'succeeded') {
			pline("Policy install: " . $package->{'name'});

			$targets = array();
			foreach ($package->{'installation-targets'} as $target) {
				array_push($targets, $target->{'uid'});
			}

			$result = $cp->send_request('install-policy', array('policy-package' => $package->{'uid'}, 'targets' => $targets));

			list($status, $percent, $details) = get_status($result->{'task-id'});
			display_details($details);
		} else {
			pline("Policy install for " . $package->{'name'} . " not started due to unsuccessful verify.");
		}

		echo "\n";
	}
}

$cp->logout();

function pline($line)
{
	echo "[" . strftime("%H:%M:%S") . "] " . $line . "\n";
}

function get_status($id)
{
	global $cp;

	$max_time = 300; // Maximum time (seconds) before timing out getting task status.

	$percent_last = -1;
	for ($i = 0; $i < ($max_time / 5); $i++) {
		list($status, $percent, $details) = $cp->task_status($id);

		if ($percent_last !== $percent) {
			pline("\tTask: " . ucwords($status) . " ($percent%)");
			$percent_last = $percent;
		}

		if ($status !== 'in progress' || $percent === 100) {
			break;
		}

		sleep(5);
	}

	if ($i >= ($max_time / 5)) {
		$status = 'timed out waiting for task';
		$details = array();
	}

	return array($status, $percent, $details);
}

function display_details($details)
{
	if (empty($details)) {
		return;
	}

	foreach ($details as $detail) {
		pline($detail->{'title'});

		if (!empty($detail->{'notifications'})) {
			pline("\tNotifications:");
			foreach ($detail->{'notifications'} as $message) {
				pline("\t $message");
			}
		}

		if (!empty($detail->{'warnings'})) {
			pline("\tWarnings:");
			foreach ($detail->{'warnings'} as $message) {
				pline("\t $message");
			}
		}

		if (!empty($detail->{'errors'})) {
			pline("\tErrors:");
			foreach ($detail->{'errors'} as $message) {
				pline("\t $message");
			}
		}
	}
}
?>
