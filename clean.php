<?php
$c = file_get_contents('public/js/app.js');
$c = preg_replace('/>>>>>>> [0-9a-f]+\r?\n/', '', $c);
file_put_contents('public/js/app.js', $c);
