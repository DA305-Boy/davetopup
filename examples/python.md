# Python (requests) Example: Create Order

```py
import requests

API_BASE = 'http://localhost:8000/api'

payload = {
    'items': [
        {'id': 'item_1', 'name': 'Diamonds', 'price': 9.99, 'quantity': 1}
    ],
    'email': 'you@example.com',
}

resp = requests.post(f"{API_BASE}/orders", json=payload)
resp.raise_for_status()
print(resp.json())
```
