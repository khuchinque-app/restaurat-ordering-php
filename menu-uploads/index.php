<?php
// Block direct access to uploaded files directory
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// Allow serving image files
