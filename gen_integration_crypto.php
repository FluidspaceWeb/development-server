<?php

// GENERATES AND INSERTS KEY IN ENV FILE USED TO ENCRYPT REFRESH_TOKEN FOR OAUTH2 INTEGRATIONS

$cryptoKey = base64_encode(sodium_crypto_secretbox_keygen());
$envPath = '/usr/src/FluidspaceDevApi/.env';
$line = 'INTEGRATION_TOKEN_CRYPTO_KEY="' .$cryptoKey. '"' . "\n";

file_put_contents($envPath, $line, FILE_APPEND | LOCK_EX);
