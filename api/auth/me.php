<?php
require_once dirname(__DIR__, 2) . '/auth.php';

$user = require_auth();
json_ok($user);
