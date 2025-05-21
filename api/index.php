<?php
/*
Usage instruction:
1. Authenticate on Repl.com and Fork this Repl (blue button on top right)
2. Click "Run" on top of appeared page
2. Copy JSON data from the "Check Roll" page on superskin.gg
3. Enter the JSON in the form with label "Enter Your Roll Data"
4. Click the "Check!" button
5. See, whether the data is correct (it should!)

OR
If you would like just to test, how does this page work, here is your sample data:
{
  "server_seed": "c4ca4238a0b92382",
  "secret_salt": "0dcc509a6f75849b",
  "public_hash": "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
  "client_seed": "my_seed",
  "nonce": "4",
  "roll": "21752"
}
*/

# ------------------------------------------------------------- #

define('ROLL_MAX', 100000000);

if(PHP_INT_SIZE !== 8) {
  throw new Exception("Only 64-bit execution environment is supported");
}

# ------------------------------------------------------------- #

function generateRoll(string $serverSeed, string $clientSeed, int $nonce): int
{
    // Create combined data string: serverSeed_clientSeed_nonce
    $combinedData = "{$serverSeed}_{$clientSeed}_{$nonce}";

    // Calculate SHA-256 hash
    $hash = hash('sha256', $combinedData);

    // Get first 16 characters of hash
    $subHash = substr($hash, 0, 16);

    // PHP's hexdec can handle hex values up to 2^62, but for very large values
    // we might need to use GMP or BCMath extension for arbitrary precision
    if (extension_loaded('gmp')) {
        // Using GMP for arbitrary precision
        $decimal = gmp_strval(gmp_init($subHash, 16));
        $roll = gmp_strval(gmp_mod(gmp_init($decimal), gmp_init(ROLL_MAX)));
        return (int)$roll;
    } elseif (extension_loaded('bcmath')) {
        // Using BCMath as a fallback
        $decimal = base_convert($subHash, 16, 10);
        $roll = bcmod($decimal, (string)ROLL_MAX);
        return (int)$roll;
    } else {
        // Basic PHP handling - may not be accurate for very large values
        $decimal = hexdec($subHash);
        $roll = $decimal % ROLL_MAX;
        return $roll;
    }
}

function calculatePublicHash(string $secret, string $salt): string
{
    // Concatenate the secret and salt before hashing (matching Go implementation)
    $combined = $secret . $salt;
    return hash('sha256', $combined);
}

# ------------------------------------------------------------- #

function checkRequiredProps(object $obj, array $props): bool
{
  foreach($props as $prop) {
    if(!isset($obj->$prop) || $obj->$prop === '') {
      return false;
    }
  }

  return true;
}

# ------------------------------------------------------------- #

function checkRegularRoll($data): string
{
  $message = "";

  $req = ['server_seed', 'secret_salt', 'public_hash', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data) || !checkRequiredProps($data, $req)) {
    return '<p class="error">Your input is invalid.<br> Try copy JSON string from the site and paste here.</p>';
  }
  if($data->server_seed[0] === '*' || $data->secret_salt[0] === '*') {
    return '<p class="warning">Server Seed seems to be not yet revealed.<br> It is impossible to verify roll right now.</p>';
  }
  
  // data seems to be valid and we can proceed
  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->server_seed, $data->client_seed, $data->nonce);

  $message .= "<p class='info'>Original Roll is: <b>{$originalRoll}</b> <br> Calculated Roll is: <b>{$calculatedRoll}</b></p>";

  if ($originalRoll === $calculatedRoll) {
    $message .= "<p class='success'>And they are identical!</p>";
  }

  $originalPublicHash = $data->public_hash;
  $calculatedPublicHash = calculatePublicHash($data->server_seed, $data->secret_salt);

  $message .= "<p class='info'>Original Public Hash is:<br><b>{$originalPublicHash}</b> <br> Valid Public Hash (for this Server Seed and Salt) is:<br><b>{$calculatedPublicHash}</b></p>";

  if ($originalPublicHash === $calculatedPublicHash) {
    $message .= "<p class='success'>And they are identical!</p>";
  }
  
  return $message;
}

$message = '';
if (!empty($_POST['roll_data'])) {
  $input = json_decode($_POST['roll_data']);
  $message = checkRegularRoll($input);
}

?>

<html>
<head>
  <title>Verify your Rolls</title>
  <style>
  body {
    margin: 0;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial;
    font-size: 0.85rem;
    line-height: 1.3;
    color: #212529;
  }

  .messages p {padding: .75rem 1.25rem; margin: 0 0 0.9rem}
  .info {color: #383d41; background-color: #e2e3e5; border-color: #d6d8db}
  .error {color: #721c24; background-color: #f8d7da; border-color: #f5c6cb}
  .warning {color: #856404; background-color: #fff3cd; border-color: #ffeeba}
  .success {color: #155724; background-color: #d4edda; border-color: #c3e6cb; font-weight: bold}
  .success {margin-top: -0.9rem !important}


  .check-form {margin-bottom: 1rem;}
  </style>
</head>
<body>
  <div class="messages">
    <?= $message ?>
  </div>

  <div class="check-form">
    <form method="post" action="/">
      <label for="roll_data">Enter Your Roll Data:</label><br>
      <textarea id="roll_data" rows="10" cols="60" name="roll_data"><?= $_POST['roll_data'] ?? '' ?></textarea>
      <button type="submit">Check!</button>
    </form>
  </div>

  <div class="footer">
    <a href="https://replit.com/@superskin.gg/provably-fair-validator" target="_blank">Source Code</a>
  </div>

  <script src="https://replit.com/public/js/replit-badge-v2.js" theme="light" position="bottom-right"></script>
</body>
</html>
