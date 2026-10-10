<?php
$path = trim((string) ($_SERVER['APP_ARGV'] ?? ($_SERVER['argv'][1] ?? '')));
if ($path === '') { exit("usage: checksyntax.php <file>\n"); }
$code = file_get_contents($path);
try {
    token_get_all($code, TOKEN_PARSE);
    echo "OK: $path\n";
} catch (ParseError $e) {
    echo "PARSE ERROR: " . $e->getMessage() . " in $path\n";
}
