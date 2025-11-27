# PHP (cURL) Example: Create Order

```php
<?php
$api = 'http://localhost:8000/api/orders';
$payload = json_encode([
  'items' => [[ 'id' => 'item_1', 'name' => 'Diamonds', 'price' => 9.99, 'quantity' => 1 ]],
  'email' => 'you@example.com'
]);

$ch = curl_init($api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($code >= 400) { echo "Error: $response\n"; }
else { echo $response; }
```
