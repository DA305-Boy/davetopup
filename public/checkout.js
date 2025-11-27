/**
 * Dave TopUp - Secure Checkout System
 * TypeScript Frontend Logic
 * Handles form validation, payment method selection, and API integration
 */

// ===== Configuration =====
const CONFIG = {
    stripePublicKey: 'pk_live_YOUR_STRIPE_PUBLIC_KEY_HERE', // Replace with your Stripe public key
    paypalClientId: 'YOUR_PAYPAL_CLIENT_ID_HERE',
    binanceApiKey: 'YOUR_BINANCE_API_KEY_HERE',
    backendUrl: 'https://www.davetopup.com/api/',
};

// ===== Global State =====
let currentPaymentMethod: string = 'stripe';
let cartData = {
    itemName: 'Free Fire Diamonds',
    quantity: 1,
    price: 9.99,
    fees: 0.50,
    discount: 0,
    uid: '123456',
};

let stripe: any = null;
let elements: any = null;
let cardElement: any = null;

// ===== Initialize =====
document.addEventListener('DOMContentLoaded', () => {
    initializeStripe();
    setupPaymentMethodListeners();
    setupFormValidation();
    updateOrderSummary();
});

// ===== Stripe Initialization =====
async function initializeStripe() {
    try {
        // Load Stripe.js
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => {
            stripe = (window as any).Stripe(CONFIG.stripePublicKey);
            elements = stripe.elements();
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '14px',
                        fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                        color: '#333',
                        '::placeholder': {
                            color: '#999',
                        },
                    },
                    invalid: {
                        color: '#d32f2f',
                    },
                },
            });
            cardElement.mount('#card-element');

            // Handle card errors
            cardElement.addEventListener('change', (e: any) => {
                const errorElement = document.getElementById('card-errors');
                if (e.error && errorElement) {
                    errorElement.textContent = e.error.message;
                } else if (errorElement) {
                    errorElement.textContent = '';
                }
            });
        };
        document.head.appendChild(script);
    } catch (error) {
        console.error('Stripe initialization failed:', error);
    }
}

// ===== Payment Method Selection =====
function setupPaymentMethodListeners() {
    const paymentOptions = document.querySelectorAll('input[name="paymentMethod"]');

    paymentOptions.forEach((option: any) => {
        option.addEventListener('change', (e: Event) => {
            currentPaymentMethod = (e.target as HTMLInputElement).value;
            updatePaymentUI();
        });
    });
}

function updatePaymentUI() {
    const stripeContainer = document.getElementById('stripeContainer');
    const cardFormContainer = document.getElementById('cardFormContainer');

    if (!stripeContainer || !cardFormContainer) return;

    switch (currentPaymentMethod) {
        case 'stripe':
        case 'stripe-apple':
        case 'google-pay':
            stripeContainer.style.display = 'block';
            cardFormContainer.style.display = 'none';
            break;
        case 'paypal':
            stripeContainer.style.display = 'none';
            cardFormContainer.style.display = 'none';
            initializePayPal();
            break;
        case 'binance':
        case 'coinbase':
        case 'crypto':
            stripeContainer.style.display = 'none';
            cardFormContainer.style.display = 'none';
            initializeCryptoPayment();
            break;
        default:
            stripeContainer.style.display = 'none';
            cardFormContainer.style.display = 'block';
    }
}

// ===== Form Validation =====
function setupFormValidation() {
    const form = document.getElementById('checkoutForm') as HTMLFormElement;
    const inputs = form.querySelectorAll('input[type="email"], input[type="text"], select');

    inputs.forEach((input: any) => {
        input.addEventListener('blur', () => {
            validateField(input);
        });
    });
}

function validateField(field: HTMLInputElement): boolean {
    const value = field.value.trim();

    switch (field.id) {
        case 'email':
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        case 'playerId':
            return value.length >= 5;
        case 'country':
            return value !== '';
        default:
            return value !== '';
    }
}

// ===== Form Submission & Checkout =====
async function handleCheckout(event: Event) {
    event.preventDefault();

    const errorElement = document.getElementById('errorMessage');
    const btnText = document.getElementById('btnText');
    const loading = document.getElementById('loading');

    if (!errorElement) return;

    // Validate form
    const form = document.getElementById('checkoutForm') as HTMLFormElement;
    if (!form.checkValidity()) {
        showError('Please fill in all required fields correctly.');
        return;
    }

    // Show loading state
    btnText!.style.display = 'none';
    loading!.style.display = 'block';

    try {
        // Get form data
        const formData = new FormData(form);
        const paymentData = {
            email: formData.get('email'),
            playerId: formData.get('playerId'),
            country: formData.get('country'),
            paymentMethod: currentPaymentMethod,
            amount: cartData.price + cartData.fees - cartData.discount,
            currency: 'USD',
            orderId: generateOrderId(),
            cartData: cartData,
        };

        // Route to appropriate payment handler
        switch (currentPaymentMethod) {
            case 'stripe':
                await handleStripePayment(paymentData);
                break;
            case 'stripe-apple':
                await handleApplePayment(paymentData);
                break;
            case 'google-pay':
                await handleGooglePayPayment(paymentData);
                break;
            case 'paypal':
                await handlePayPalPayment(paymentData);
                break;
            case 'binance':
                await handleBinancePayment(paymentData);
                break;
            case 'coinbase':
                await handleCoinbasePayment(paymentData);
                break;
            case 'crypto':
                await handleCryptoPayment(paymentData);
                break;
            default:
                await handleAlternativePayment(paymentData);
        }
    } catch (error) {
        console.error('Checkout error:', error);
        showError(
            error instanceof Error
                ? error.message
                : 'An error occurred during checkout. Please try again.'
        );
    } finally {
        // Hide loading state
        btnText!.style.display = 'block';
        loading!.style.display = 'none';
    }
}

// ===== Payment Handlers =====
async function handleStripePayment(paymentData: any) {
    if (!stripe || !cardElement) {
        showError('Payment system not initialized. Please refresh and try again.');
        return;
    }

    try {
        // Create payment method
        const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
                email: paymentData.email,
                name: paymentData.playerId,
            },
        });

        if (error) {
            showError(error.message);
            return;
        }

        // Send to backend
        const response = await sendPaymentToBackend({
            ...paymentData,
            stripePaymentMethodId: paymentMethod.id,
        });

        if (response.success) {
            redirectToSuccess(response.orderId);
        } else {
            showError(response.message || 'Payment failed. Please try again.');
        }
    } catch (error) {
        showError(error instanceof Error ? error.message : 'Payment processing failed.');
    }
}

async function handleApplePayment(paymentData: any) {
    if (!stripe) {
        showError('Apple Pay not available.');
        return;
    }

    try {
        const paymentRequest = stripe.paymentRequest({
            country: paymentData.country,
            currency: 'usd',
            total: {
                label: 'Dave TopUp',
                amount: Math.round(paymentData.amount * 100),
            },
            requestPayerEmail: true,
        });

        const prButton = elements.create('paymentRequestButton', {
            paymentRequest: paymentRequest,
        });

        const response = await sendPaymentToBackend({
            ...paymentData,
            applePayToken: 'apple_pay_token',
        });

        if (response.success) {
            redirectToSuccess(response.orderId);
        }
    } catch (error) {
        showError('Apple Pay payment failed.');
    }
}

async function handleGooglePayPayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend({
            ...paymentData,
            googlePayToken: 'google_pay_token',
        });

        if (response.success) {
            redirectToSuccess(response.orderId);
        }
    } catch (error) {
        showError('Google Pay payment failed.');
    }
}

async function handlePayPalPayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend({
            ...paymentData,
            paymentMethod: 'paypal',
        });

        if (response.redirectUrl) {
            window.location.href = response.redirectUrl;
        } else if (response.success) {
            redirectToSuccess(response.orderId);
        }
    } catch (error) {
        showError('PayPal payment failed.');
    }
}

async function handleBinancePayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend({
            ...paymentData,
            paymentMethod: 'binance',
        });

        if (response.redirectUrl) {
            window.location.href = response.redirectUrl;
        }
    } catch (error) {
        showError('Binance Pay failed.');
    }
}

async function handleCoinbasePayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend({
            ...paymentData,
            paymentMethod: 'coinbase',
        });

        if (response.redirectUrl) {
            window.location.href = response.redirectUrl;
        }
    } catch (error) {
        showError('Coinbase payment failed.');
    }
}

async function handleCryptoPayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend({
            ...paymentData,
            paymentMethod: 'crypto',
        });

        if (response.walletAddress) {
            alert(
                `Send ${paymentData.amount} USD to: ${response.walletAddress}\nOrder ID: ${paymentData.orderId}`
            );
        }
    } catch (error) {
        showError('Crypto payment setup failed.');
    }
}

async function handleAlternativePayment(paymentData: any) {
    try {
        const response = await sendPaymentToBackend(paymentData);

        if (response.success) {
            redirectToSuccess(response.orderId);
        } else {
            showError(response.message || 'Payment failed.');
        }
    } catch (error) {
        showError('Payment processing failed.');
    }
}

// ===== Backend Communication =====
async function sendPaymentToBackend(paymentData: any): Promise<any> {
    const response = await fetch(`${CONFIG.backendUrl}checkout.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(paymentData),
    });

    if (!response.ok) {
        throw new Error(`Server error: ${response.statusText}`);
    }

    return response.json();
}

// ===== Utility Functions =====
function showError(message: string) {
    const errorElement = document.getElementById('errorMessage');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
        setTimeout(() => {
            errorElement.classList.remove('show');
        }, 5000);
    }
}

function showSuccess(message: string) {
    const successElement = document.getElementById('successMessage');
    if (successElement) {
        successElement.textContent = message;
        successElement.classList.add('show');
    }
}

function generateOrderId(): string {
    return `ORD-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

function redirectToSuccess(orderId: string) {
    window.location.href = `https://www.davetopup.com/public/success.html?orderId=${orderId}`;
}

function applyPromo() {
    const promoCode = (document.getElementById('promoCode') as HTMLInputElement).value.trim();

    if (!promoCode) {
        showError('Please enter a promo code.');
        return;
    }

    // TODO: Validate promo code with backend
    showSuccess(`Promo code "${promoCode}" applied successfully!`);
}

function updateOrderSummary() {
    const subtotal = cartData.price;
    const fees = cartData.fees;
    const discount = cartData.discount;
    const total = subtotal + fees - discount;

    (document.getElementById('subtotal') as HTMLElement).textContent = `$${subtotal.toFixed(2)}`;
    (document.getElementById('fees') as HTMLElement).textContent = `$${fees.toFixed(2)}`;
    (document.getElementById('discount') as HTMLElement).textContent = `-$${discount.toFixed(2)}`;
    (document.getElementById('total') as HTMLElement).textContent = `$${total.toFixed(2)}`;

    const btn = document.querySelector('.btn-primary') as HTMLButtonElement;
    if (btn) {
        const btnText = btn.querySelector('#btnText') as HTMLElement;
        if (btnText) {
            btnText.textContent = `Proceed to Pay $${total.toFixed(2)}`;
        }
    }
}

// ===== Initialize PayPal =====
function initializePayPal() {
    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${CONFIG.paypalClientId}`;
    script.async = true;
    script.onload = () => {
        // PayPal SDK loaded - ready for payment
    };
    document.body.appendChild(script);
}

// ===== Initialize Crypto Payment =====
function initializeCryptoPayment() {
    console.log('Crypto payment initialized');
    // Load crypto payment scripts if needed
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        handleCheckout,
        generateOrderId,
        validateField,
    };
}
