# TypeScript Example: Create Order (fetch)

```ts
// Example: create order and redirect to checkout
const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8000/api';

async function createOrder(orderPayload: any) {
  const res = await fetch(`${API_BASE}/orders`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(orderPayload),
  });
  if (!res.ok) throw new Error('Failed to create order');
  return res.json();
}

// Usage
createOrder({ items: [{ id: 'item_1', name: 'Diamonds', price: 9.99, quantity: 1 }], email: 'you@example.com' })
  .then(data => console.log('order created', data))
  .catch(err => console.error(err));
```
