# Pweza API - Laravel 11 Soccer Prediction Backend

A Laravel 11 API backend for a mobile soccer prediction app called **Pweza**. The app allows tipsters to post daily predictions and users to subscribe to view them.

## 🚀 Features

- **User Roles**: Customer, Tipster, Admin
- **Authentication**: Laravel Sanctum (Token-based)
- **Database**: PostgreSQL with proper relationships
- **File Storage**: Local storage for prediction images
- **Subscriptions**: Daily, Weekly, Monthly plans
- **Payment Integration**: Ready for payment providers
- **Timezone**: Africa/Dar_es_Salaam

## 📋 Requirements

- PHP 8.2+
- Laravel 11
- PostgreSQL
- Composer

## 🛠️ Installation

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database configuration**
   Update your `.env` file:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=pweza_api_db
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   FILESYSTEM_DISK=public
   ```

4. **Create database**
   ```bash
   createdb pweza_api_db
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Create storage link**
   ```bash
   php artisan storage:link
   ```

7. **Seed test data**
   ```bash
   php artisan db:seed
   ```

8. **Start the server**
   ```bash
   php artisan serve
   ```

## 🗄️ Database Schema

### Users Table
- `id`, `name`, `phone_number` (unique), `role` (customer/tipster/admin), `password`, `timestamps`

### Predictions Table
- `id`, `tipster_id`, `title`, `description`, `image_url`, `booking_codes` (JSON)
- `odds_total`, `kickoff_at`, `confidence_level`, `is_premium`, `status`, `result_status`
- `result_notes`, `publish_at`, `lock_at`, `timestamps`

### Subscriptions Table
- `id`, `user_id`, `tipster_id`, `plan_type`, `price`, `currency`
- `start_at`, `end_at`, `status`, `timestamps`

### Payment Transactions Table
- `id`, `user_id`, `subscription_id`, `amount`, `currency`, `provider`
- `provider_txn_id`, `status`, `raw_payload` (JSON), `timestamps`

## 🔐 API Endpoints

### Authentication (Dual Support: Password + OTP)

#### Password-based Authentication
```
POST /api/register                   - Register with password
POST /api/login                      - Login with password
```

#### OTP-based Authentication
```
POST /api/auth/send-otp              - Send OTP to phone number
POST /api/auth/verify-otp            - Verify OTP and login/register
```

#### Common Auth Routes
```
POST /api/logout                     - Logout user
GET  /api/me                         - Get authenticated user
```

### Predictions
```
GET  /api/predictions                 - Public listing of all predictions
POST /api/predictions                - Create prediction (tipster only)
```

### Payments (Selcom Integration)
```
POST /api/payments/initiate          - Initiate payment for subscription
POST /api/payments/selcom-webhook    - Selcom payment webhook (public)
```

### Withdrawals (Tipster Earnings)
```
GET  /api/withdrawals                - Get tipster's withdrawal requests
POST /api/withdrawals                - Create withdrawal request (tipster only)
GET  /api/withdrawals/{id}           - Get withdrawal request details
PATCH /api/withdrawals/{id}/cancel   - Cancel pending withdrawal request
```

## 📱 Test Users

The seeder creates these test users with both authentication methods:

**Tipster User**
- Phone: `1234567890`
- Password: `password123`
- Role: `tipster`

**Customer User**
- Phone: `0987654321`
- Password: `password123`
- Role: `customer`

**Admin User**
- Phone: `1122334455`
- Password: `password123`
- Role: `admin`

**Authentication Options:**
- Password login: Use phone + password
- OTP login: Send OTP to any phone number

## 🧪 Testing

Run the test script:
```bash
php test_api.php
```

Or use tools like Postman/Insomnia to test the endpoints.

### Example: Login Request
```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "1234567890", "password": "password123"}'
```

### Example: Create Prediction (with Bearer token)
```bash
curl -X POST http://127.0.0.1:8000/api/predictions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "title": "Arsenal vs Chelsea - Over 2.5 Goals",
    "description": "Both teams have strong attacking formations",
    "odds_total": 1.85,
    "kickoff_at": "2025-07-26 15:00:00",
    "confidence_level": 8,
    "is_premium": false,
    "status": "draft",
    "result_status": "pending"
  }'
```

## 🔒 Authentication

All protected routes require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

Get the token by logging in through `/api/login`.

## 📝 Business Logic

### Prediction Status Flow
1. **Draft** → **Published** → **Locked** → **Graded**
2. Predictions can only be edited before being locked
3. Premium predictions require active subscriptions

### Subscription Logic
- Users can subscribe to tipsters with daily/weekly/monthly plans
- Active subscriptions allow access to premium predictions
- Subscription status is checked for premium content access

### Payment Flow
1. Create subscription → Initiate payment → Process webhook → Activate subscription

### Withdrawal System
- **Minimum Withdrawal Limit**: Configurable minimum amount (default: 1000 TZS)
- **Earnings Calculation**: Based on active subscription commissions
- **Available Balance**: Total earnings minus pending/paid withdrawals
- **Request Flow**: Pending → Paid/Rejected (with admin approval)
- **Status Tracking**: pending, paid, rejected, cancelled
- **Admin Actions**: View, approve, reject, mark as paid with notes

## 🚀 Deployment

For production deployment:
1. Set up PostgreSQL database
2. Configure environment variables
3. Set up proper file storage (AWS S3, etc.)
4. Configure payment provider webhooks
5. Set up proper logging and monitoring

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
