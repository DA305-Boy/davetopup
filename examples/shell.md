# Shell (curl) Example: Create Order

```sh
API_BASE="http://localhost:8000/api"

curl -X POST "$API_BASE/orders" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"id":"item_1","name":"Diamonds","price":9.99,"quantity":1}],"email":"you@example.com"}'
```
