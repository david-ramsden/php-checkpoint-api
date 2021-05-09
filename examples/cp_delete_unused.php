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

include('../CPAPI.class.php');

$cp = new CPAPI(array('server'          => '10.10.10.134',
                      'user'            => 'svc_cpapi',
                      'password'        => 'th3_pa55w0rd',
                      'session-timeout' => 30));

$unused = $cp->send_request('show-unused-objects', array('limit' => 500, 'offset' => 0, 'details-level' => 'standard'));

foreach($unused->objects as $obj) {
	switch($obj->type) {
		case 'host':
			echo "Deleting host $obj->name: ";
			try {
				$result = $cp->send_request('delete-host', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'network':
			echo "Deleting network $obj->name: ";
			try {
				$result = $cp->send_request('delete-network', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'service-group':
			echo "Deleting service group $obj->name: ";
			try {
				$result = $cp->send_request('delete-service-group', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'service-udp':
			echo "Deleting service-udp $obj->name: ";
			try {
				$result = $cp->send_request('delete-service-udp', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'service-tcp':
			echo "Deleting service-tcp $obj->name: ";
			try {
				$result = $cp->send_request('delete-service-tcp', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'address-range':
			echo "Deleting address-range $obj->name: ";
			try {
				$result = $cp->send_request('delete-address-range', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'group':
			echo "Deleting group $obj->name: ";
			try {
				$result = $cp->send_request('delete-group', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		case 'time':
			echo "Deleting time $obj->name: ";
			try {
				$result = $cp->send_request('delete-time', array('uid' => $obj->uid));
				echo $result->message . "\n";
			} catch (Exception $e) {
				echo "Failed\n";
			}
			break;

		default:
			echo "Object $obj->name has unknown type $obj->type\n";
			break;
	}
}

$cp->publish();
$cp->logout();
?>
