<?php
// Contain function files
require_once 'includes/functions.php';

// Destroy the session
session_destroy();

// Redirect to the login page
redirect('login.php');
?> 