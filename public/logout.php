<?php
// public/logout.php
require_once __DIR__ . '/../bootstrap.php';
Auth::logout();
H::redirect(APP_URL . '/login.php');
