# PHP Class for Check Point Management API

## Introduction
This PHP class allows interaction with the [Check Point Management API](https://sc1.checkpoint.com/documents/latest/APIs/#introduction~v1.3%20). It has been tested against Check Point Management API v1.3 (i.e. R80.20). The class does not implement specific Check Point Management API calls but allows for faster development of scripts by providing a basic framework. Scripts using this PHP class can either be CLI or web based.

Included in this repository are two sciprts that use the PHP class.

## Examples

### cp_delete_unused.php
This script will connect to a Security Management Server and delete up to 500 unused objects including hosts, networks, groups, ranges, services and time objects. It should be run from the CLI (`php ./cp_delete_unused.php`), for example from a cron job.

## cp_policy_install.php
This script will connect to a Security Management Server, enumerate every policy, verify the policy and if verification is successful will install the policy to the targets. It should be run from the CLI (`php ./cp_policy_install.php`), for example from a cron job.
