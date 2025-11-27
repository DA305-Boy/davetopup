// src/components/Checkout/Checkout.tsx
import React, { useState, useCallback, useMemo, useEffect } from 'react';
import {
  loadStripe,
  Stripe,
  StripeElements,
  StripeCardElement,
} from '@stripe/js';
import {
  Elements,
  CardElement,
  useStripe,
  useElements,
} from '@stripe/react-stripe-js';
import './Checkout.css';

// Resolve API base for Vite/Cra compatibility
const API_URL = (import.meta as any).env.VITE_API_URL || (import.meta as any).env.VITE_API_BASE || (window as any).REACT_APP_API_BASE || 'http://localhost:8000/api';

// ===== Types =====
interface OrderItem {
  id: string;
  name: string;
  game: string;
  price: number;
  quantity: number;
  playerUid: string;
}

interface Order {
  id: string;
  items: OrderItem[];
  subtotal: number;
  tax: number;
  total: number;
}

interface PaymentFormData {
  email: string;
  phone?: string;
  playerUid: string;
  playerNickname: string;
  paymentMethod: 'card' | 'paypal' | 'binance' | 'voucher';
  cardData?: {
    fullName: string;
    cardNumber: string;
    expiry: string;
    cvc: string;
  };
  voucherCode?: string;
}

interface PaymentState {
  isProcessing: boolean;
  error: string | null;
  success: boolean;
  orderIdCreated: string | null;
}

// ===== Main Checkout Component =====
export const Checkout: React.FC<{ order: Order }> = ({ order }) => {
  const stripePromise = useMemo(
    () =>
      loadStripe((import.meta as any).env.VITE_STRIPE_PUBLIC_KEY || process.env.REACT_APP_STRIPE_PUBLIC_KEY || ''),
    []
  );

  return (
    <Elements stripe={stripePromise}>
      <CheckoutForm order={order} />
    </Elements>
  );
};

// ===== Checkout Form Component =====
const CheckoutForm: React.FC<{ order: Order }> = ({ order }) => {
  const stripe = useStripe();
  const elements = useElements();

  // State Management
  const [formData, setFormData] = useState<PaymentFormData>({
    email: '',
    phone: '',
    playerUid: '',
    playerNickname: '',
    paymentMethod: 'card',
  });

  const [paymentState, setPaymentState] = useState<PaymentState>({
    isProcessing: false,
    error: null,
    success: false,
    orderIdCreated: null,
  });

  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [cardComplete, setCardComplete] = useState(false);

  // ===== Validation =====
  const validateEmail = (email: string): boolean => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  const validatePhone = (phone: string): boolean => {
    if (!phone) return true; // Optional
    return /^[\d\s\-\+\(\)]{10,}$/.test(phone);
  };

  const validatePlayerUid = (uid: string): boolean => {
    return uid.trim().length >= 3 && uid.trim().length <= 50;
  };

  const validateVoucherCode = (code: string): boolean => {
    return /^[A-Z0-9\-]{6,}$/.test(code.toUpperCase());
  };

  const validateFormData = useCallback((): boolean => {
    const errors: Record<string, string> = {};

    // Email validation
    if (!validateEmail(formData.email)) {
      errors.email = 'Please enter a valid email address';
    }

    // Phone validation
    if (formData.phone && !validatePhone(formData.phone)) {
      errors.phone = 'Please enter a valid phone number';
    }

    // Player UID validation
    if (!validatePlayerUid(formData.playerUid)) {
      errors.playerUid = 'Player ID must be 3-50 characters';
    }

    // Player nickname validation
    if (!formData.playerNickname.trim()) {
      errors.playerNickname = 'Player nickname is required';
    }

    // Payment method specific validation
    if (formData.paymentMethod === 'voucher') {
      if (!formData.voucherCode || !validateVoucherCode(formData.voucherCode)) {
        errors.voucherCode = 'Please enter a valid voucher code';
      }
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  }, [formData]);

  // ===== API Calls =====
  const createOrder = useCallback(async (): Promise<string | null> => {
    try {
      const response = await fetch(
        `${API_URL}/orders`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            items: order.items,
            email: formData.email,
            playerUid: formData.playerUid,
            playerNickname: formData.playerNickname,
            phone: formData.phone,
          }),
        }
      );

      if (!response.ok) {
        throw new Error('Failed to create order');
      }

      const data = await response.json();
      return data.orderId;
    } catch (err) {
      setPaymentState((prev) => ({
        ...prev,
        error: err instanceof Error ? err.message : 'Failed to create order',
      }));
      return null;
    }
  }, [formData, order.items]);

  const processCardPayment = useCallback(
    async (orderId: string) => {
      if (!stripe || !elements) return;

      try {
        // Create token from card element
        const { token, error } = await stripe.createToken(
          elements.getElement(CardElement)!
        );

        if (error) {
          throw new Error(error.message);
        }

        // Send token to backend
        const response = await fetch(
          `${API_URL}/payments/card`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              orderId,
              stripeToken: token.id,
              amount: order.total,
              currency: 'usd',
            }),
          }
        );

        if (!response.ok) {
          throw new Error('Payment failed');
        }

        const data = await response.json();

        if (data.status === 'succeeded') {
          setPaymentState({
            isProcessing: false,
            error: null,
            success: true,
            orderIdCreated: orderId,
          });
          window.location.href = `/success?orderId=${orderId}`;
        } else if (data.status === 'requires_action') {
          // Handle 3D Secure or similar
          await handle3DSecure(data.clientSecret);
        }
      } catch (err) {
        setPaymentState((prev) => ({
          ...prev,
          isProcessing: false,
          error: err instanceof Error ? err.message : 'Payment failed',
        }));
      }
    },
    [stripe, elements, order.total]
  );

  const processPayPalPayment = useCallback(
    async (orderId: string) => {
      try {
        const response = await fetch(
          `${API_URL}/payments/paypal`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              orderId,
              amount: order.total,
            }),
          }
        );

        if (!response.ok) {
          throw new Error('Failed to initiate PayPal payment');
        }

        const data = await response.json();
        window.location.href = data.approvalUrl;
      } catch (err) {
        setPaymentState((prev) => ({
          ...prev,
          isProcessing: false,
          error: err instanceof Error ? err.message : 'PayPal failed',
        }));
      }
    },
    [order.total]
  );

  const processBinancePayment = useCallback(
    async (orderId: string) => {
      try {
        const response = await fetch(
          `${API_URL}/payments/binance`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              orderId,
              amount: order.total,
            }),
          }
        );

        if (!response.ok) {
          throw new Error('Failed to initiate Binance Pay');
        }

        const data = await response.json();
        window.location.href = data.checkoutUrl;
      } catch (err) {
        setPaymentState((prev) => ({
          ...prev,
          isProcessing: false,
          error: err instanceof Error ? err.message : 'Binance Pay failed',
        }));
      }
    },
    [order.total]
  );

  const processVoucherPayment = useCallback(
    async (orderId: string) => {
      try {
        const response = await fetch(
          `${API_URL}/payments/voucher`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              orderId,
              voucherCode: formData.voucherCode?.toUpperCase(),
            }),
          }
        );

        if (!response.ok) {
          throw new Error('Voucher validation failed');
        }

        const data = await response.json();

        if (data.status === 'success') {
          setPaymentState({
            isProcessing: false,
            error: null,
            success: true,
            orderIdCreated: orderId,
          });
          window.location.href = `/success?orderId=${orderId}`;
        } else if (data.status === 'pending') {
          // Manual verification required
          window.location.href = `/pending?orderId=${orderId}`;
        }
      } catch (err) {
        setPaymentState((prev) => ({
          ...prev,
          isProcessing: false,
          error: err instanceof Error ? err.message : 'Voucher failed',
        }));
      }
    },
    [formData.voucherCode]
  );

  const handle3DSecure = useCallback(async (clientSecret: string) => {
    if (!stripe) return;

    try {
      const result = await stripe.handleCardAction(clientSecret);
      if (result.error) {
        throw new Error(result.error.message);
      }
      // Redirect to success
      window.location.href = `/success?orderId=${paymentState.orderIdCreated}`;
    } catch (err) {
      setPaymentState((prev) => ({
        ...prev,
        isProcessing: false,
        error: err instanceof Error ? err.message : '3D Secure failed',
      }));
    }
  }, [stripe, paymentState.orderIdCreated]);

  // ===== Main Submit Handler =====
  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();

      if (!validateFormData()) return;

      setPaymentState({
        isProcessing: true,
        error: null,
        success: false,
        orderIdCreated: null,
      });

      // Create order first
      const orderId = await createOrder();
      if (!orderId) return;

      // Process payment based on method
      switch (formData.paymentMethod) {
        case 'card':
          await processCardPayment(orderId);
          break;
        case 'paypal':
          await processPayPalPayment(orderId);
          break;
        case 'binance':
          await processBinancePayment(orderId);
          break;
        case 'voucher':
          await processVoucherPayment(orderId);
          break;
      }
    },
    [
      validateFormData,
      createOrder,
      formData.paymentMethod,
      processCardPayment,
      processPayPalPayment,
      processBinancePayment,
      processVoucherPayment,
    ]
  );

  // ===== Input Handlers =====
  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
      const { name, value } = e.target;
      setFormData((prev) => ({
        ...prev,
        [name]: value,
      }));

      // Clear error for this field
      if (formErrors[name]) {
        setFormErrors((prev) => {
          const next = { ...prev };
          delete next[name];
          return next;
        });
      }
    },
    [formErrors]
  );

  const handleCardChange = (event: any) => {
    if (event.complete) {
      setCardComplete(true);
    }
    if (event.error) {
      setFormErrors((prev) => ({
        ...prev,
        cardElement: event.error.message,
      }));
    } else {
      setFormErrors((prev) => {
        const next = { ...prev };
        delete next.cardElement;
        return next;
      });
    }
  };

  return (
    <div className="checkout-container">
      {/* Header */}
      <div className="checkout-header">
        <h1>Secure Checkout</h1>
        <div className="security-badge">
          🔒 SSL Encrypted & PCI Compliant
        </div>
      </div>

      <div className="checkout-main">
        {/* Order Summary */}
        <OrderSummary order={order} />

        {/* Checkout Form */}
        <form onSubmit={handleSubmit} className="checkout-form">
          {/* Error Display */}
          {paymentState.error && (
            <div className="alert alert-error">
              <span className="alert-icon">✕</span>
              {paymentState.error}
            </div>
          )}

          {/* Player Info Section */}
          <div className="form-section">
            <h2>Player Information</h2>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="email">Email Address *</label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  placeholder="your@email.com"
                  required
                  disabled={paymentState.isProcessing}
                />
                {formErrors.email && (
                  <span className="error-message">{formErrors.email}</span>
                )}
              </div>

              <div className="form-group">
                <label htmlFor="phone">Phone (Optional)</label>
                <input
                  id="phone"
                  name="phone"
                  type="tel"
                  value={formData.phone}
                  onChange={handleInputChange}
                  placeholder="+1 (555) 000-0000"
                  disabled={paymentState.isProcessing}
                />
                {formErrors.phone && (
                  <span className="error-message">{formErrors.phone}</span>
                )}
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="playerUid">Player ID / UID *</label>
                <input
                  id="playerUid"
                  name="playerUid"
                  type="text"
                  value={formData.playerUid}
                  onChange={handleInputChange}
                  placeholder="123456789"
                  required
                  disabled={paymentState.isProcessing}
                />
                {formErrors.playerUid && (
                  <span className="error-message">{formErrors.playerUid}</span>
                )}
              </div>

              <div className="form-group">
                <label htmlFor="playerNickname">In-Game Nickname *</label>
                <input
                  id="playerNickname"
                  name="playerNickname"
                  type="text"
                  value={formData.playerNickname}
                  onChange={handleInputChange}
                  placeholder="YourNickname"
                  required
                  disabled={paymentState.isProcessing}
                />
                {formErrors.playerNickname && (
                  <span className="error-message">
                    {formErrors.playerNickname}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Payment Method Selection */}
          <PaymentMethodSelector
            selectedMethod={formData.paymentMethod}
            onChange={(method) =>
              setFormData((prev) => ({
                ...prev,
                paymentMethod: method as PaymentFormData['paymentMethod'],
              }))
            }
            disabled={paymentState.isProcessing}
          />

          {/* Payment Method Specific Forms */}
          {formData.paymentMethod === 'card' && (
            <CardPaymentForm
              cardComplete={cardComplete}
              onCardChange={handleCardChange}
              error={formErrors.cardElement}
              disabled={paymentState.isProcessing}
            />
          )}

          {formData.paymentMethod === 'voucher' && (
            <VoucherForm
              voucherCode={formData.voucherCode || ''}
              onVoucherChange={(code) =>
                setFormData((prev) => ({ ...prev, voucherCode: code }))
              }
              error={formErrors.voucherCode}
              disabled={paymentState.isProcessing}
            />
          )}

          {/* Submit Button */}
          <button
            type="submit"
            className="btn-submit"
            disabled={
              paymentState.isProcessing ||
              (formData.paymentMethod === 'card' && !cardComplete)
            }
          >
            {paymentState.isProcessing ? (
              <>
                <span className="spinner"></span>
                Processing...
              </>
            ) : (
              `Pay $${order.total.toFixed(2)}`
            )}
          </button>

          {/* Terms */}
          <p className="terms-text">
            By completing this purchase, you agree to our Terms of Service and
            Privacy Policy. Your payment is secure and encrypted.
          </p>
        </form>
      </div>
    </div>
  );
};

// ===== Sub-Components =====
const OrderSummary: React.FC<{ order: Order }> = ({ order }) => (
  <div className="order-summary">
    <h3>Order Summary</h3>
    {order.items.map((item) => (
      <div key={item.id} className="order-item">
        <div className="item-info">
          <strong>{item.name}</strong>
          <span className="item-game">{item.game}</span>
        </div>
        <div className="item-price">${(item.price * item.quantity).toFixed(2)}</div>
      </div>
    ))}
    <div className="order-divider"></div>
    <div className="order-total-row">
      <span>Subtotal</span>
      <span>${order.subtotal.toFixed(2)}</span>
    </div>
    <div className="order-total-row">
      <span>Tax</span>
      <span>${order.tax.toFixed(2)}</span>
    </div>
    <div className="order-total-row final">
      <strong>Total</strong>
      <strong>${order.total.toFixed(2)}</strong>
    </div>
  </div>
);

const PaymentMethodSelector: React.FC<{
  selectedMethod: string;
  onChange: (method: string) => void;
  disabled: boolean;
}> = ({ selectedMethod, onChange, disabled }) => (
  <div className="form-section">
    <h2>Payment Method</h2>
    <div className="payment-methods">
      {[
        { id: 'card', label: 'Card', icon: '💳' },
        { id: 'paypal', label: 'PayPal', icon: '🅿️' },
        { id: 'binance', label: 'Binance Pay', icon: '₿' },
        { id: 'voucher', label: 'Gift Card', icon: '🎁' },
      ].map((method) => (
        <label key={method.id} className="payment-method">
          <input
            type="radio"
            name="paymentMethod"
            value={method.id}
            checked={selectedMethod === method.id}
            onChange={() => onChange(method.id)}
            disabled={disabled}
          />
          <span className="method-label">
            <span className="method-icon">{method.icon}</span>
            <span className="method-text">{method.label}</span>
          </span>
        </label>
      ))}
    </div>
  </div>
);

const CardPaymentForm: React.FC<{
  cardComplete: boolean;
  onCardChange: (event: any) => void;
  error?: string;
  disabled: boolean;
}> = ({ cardComplete, onCardChange, error, disabled }) => (
  <div className="form-section">
    <h2>Card Details</h2>
    <div className={`card-element-wrapper ${error ? 'error' : ''}`}>
      <CardElement
        onChange={onCardChange}
        options={{
          disabled,
          style: {
            base: {
              fontSize: '16px',
              color: '#333',
              fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto',
            },
            invalid: { color: '#dc3545' },
          },
        }}
      />
    </div>
    {error && <span className="error-message">{error}</span>}
  </div>
);

const VoucherForm: React.FC<{
  voucherCode: string;
  onVoucherChange: (code: string) => void;
  error?: string;
  disabled: boolean;
}> = ({ voucherCode, onVoucherChange, error, disabled }) => (
  <div className="form-section">
    <h2>Voucher / Gift Card</h2>
    <div className="form-group">
      <label htmlFor="voucherCode">Enter Code *</label>
      <input
        id="voucherCode"
        type="text"
        value={voucherCode}
        onChange={(e) => onVoucherChange(e.target.value.toUpperCase())}
        placeholder="XXXX-XXXX-XXXX"
        disabled={disabled}
        maxLength={50}
      />
      {error && <span className="error-message">{error}</span>}
    </div>
  </div>
);

export default Checkout;
