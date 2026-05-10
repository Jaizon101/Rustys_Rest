<?php
echo "MYSQLHOST: " . getenv('MYSQLHOST') . "<br>";
echo "MYSQLPORT: " . getenv('MYSQLPORT') . "<br>";
echo "MYSQLDATABASE: " . getenv('MYSQLDATABASE') . "<br>";
echo "MYSQLUSER: " . getenv('MYSQLUSER') . "<br>";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? 'SET' : 'NOT SET') . "<br>";