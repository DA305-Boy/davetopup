# Kotlin (Ktor client) Example: Create Order

```kotlin
import io.ktor.client.*
import io.ktor.client.request.*
import io.ktor.client.engine.cio.*
import io.ktor.client.statement.*
import io.ktor.http.*

suspend fun createOrder() {
    val client = HttpClient(CIO)
    val api = "http://localhost:8000/api/orders"
    val payload = mapOf(
        "items" to listOf(mapOf("id" to "item_1", "name" to "Diamonds", "price" to 9.99, "quantity" to 1)),
        "email" to "you@example.com"
    )
    val response: HttpResponse = client.post(api) {
        contentType(ContentType.Application.Json)
        setBody(payload)
    }
    println(response.status)
}
```
