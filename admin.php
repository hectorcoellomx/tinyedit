<?php
require_once 'model.php';

$user = new User();
var_dump($user->getAll());