<?php
echo "DIR=", __DIR__, " | cwd=", getcwd(), " | APP_ROOT=", getenv('APP_ROOT'), " | argv=", getenv('APP_ARGV'), "\n";
echo "db exists: ", var_export(file_exists(dirname(__DIR__) . '/database.sql'), true), "\n";
echo "hash ok: ", var_export(password_verify('Admin@12345', password_hash('Admin@12345', PASSWORD_BCRYPT)), true), "\n";
