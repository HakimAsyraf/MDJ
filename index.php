<?php
require_once __DIR__ . '/includes/config.php';
if (is_logged_in()) {
  redirect('/dashboard.php');
}
redirect('/login.php');
