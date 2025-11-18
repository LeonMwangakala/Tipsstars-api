<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PredictionController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\TipsterPublicController;
use App\Http\Controllers\API\TipsterRatingController;
use App\Http\Controllers\API\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
// Password-based authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// OTP-based authentication
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

// Tipster registration
Route::post('/auth/register-tipster', [AuthController::class, 'registerTipster']);

// Public content
Route::get('/predictions', [PredictionController::class, 'publicIndex']);

// Public rating routes
Route::get('/ratings/top', [TipsterRatingController::class, 'topRated']);
Route::get('/ratings/leaderboard', [TipsterRatingController::class, 'leaderboard']);
Route::get('/ratings/tipster/{id}', [TipsterRatingController::class, 'show']);

// Payment webhook (should be public for payment providers)
Route::post('/payments/selcom-webhook', [PaymentController::class, 'webhook']);

// Admin routes (public login, protected operations)
Route::post('/admin/login', [AdminController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/admin/tipsters', [AdminController::class, 'tipsters']);
        Route::patch('/admin/tipsters/{id}/approve', [AdminController::class, 'approveTipster']);
        Route::patch('/admin/tipsters/{id}/reject', [AdminController::class, 'rejectTipster']);
        Route::get('/admin/predictions', [AdminController::class, 'predictions']);
        Route::post('/admin/predictions', [AdminController::class, 'createPrediction']);
        Route::patch('/admin/predictions/{id}', [AdminController::class, 'updatePrediction']);
        Route::delete('/admin/predictions/{id}', [AdminController::class, 'deletePrediction']);
        Route::get('/admin/customers', [AdminController::class, 'customers']);
        Route::get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
        Route::post('/admin/subscriptions', [AdminController::class, 'createSubscription']);
        Route::patch('/admin/subscriptions/{id}/status', [AdminController::class, 'updateSubscriptionStatus']);
        Route::get('/admin/bookers', [AdminController::class, 'bookers']);
        Route::post('/admin/bookers', [AdminController::class, 'createBooker']);
        Route::patch('/admin/bookers/{id}', [AdminController::class, 'updateBooker']);
        Route::delete('/admin/bookers/{id}', [AdminController::class, 'deleteBooker']);
        Route::post('/admin/register-user', [AdminController::class, 'registerUser']);
        Route::get('/admin/tipsters/{id}/id-document', [AdminController::class, 'getTipsterIdDocument']);
Route::patch('/admin/tipsters/{id}', [AdminController::class, 'updateTipster']);
        Route::get('/admin/notifications', [AdminController::class, 'getNotifications']);
        Route::post('/admin/notifications', [AdminController::class, 'sendNotification']);
        
        // Admin Users Management
        Route::get('/admin/users', [AdminController::class, 'adminUsers']);
        Route::post('/admin/users', [AdminController::class, 'createAdmin']);
        Route::patch('/admin/users/{id}', [AdminController::class, 'updateAdmin']);
        Route::delete('/admin/users/{id}', [AdminController::class, 'deleteAdmin']);
        Route::patch('/admin/users/{id}/toggle-status', [AdminController::class, 'toggleAdminStatus']);
        
        // Commission Management
        Route::get('/admin/commission-configs', [AdminController::class, 'commissionConfigs']);
        Route::post('/admin/commission-configs', [AdminController::class, 'createCommissionConfig']);
        Route::patch('/admin/commission-configs/{id}', [AdminController::class, 'updateCommissionConfig']);
        Route::delete('/admin/commission-configs/{id}', [AdminController::class, 'deleteCommissionConfig']);
        Route::get('/admin/commission-stats', [AdminController::class, 'commissionStats']);
        
        // Withdrawal management routes
        Route::get('/admin/withdrawals', [AdminController::class, 'withdrawalRequests']);
        Route::get('/admin/withdrawals/{id}', [AdminController::class, 'withdrawalRequest']);
        Route::patch('/admin/withdrawals/{id}/mark-paid', [AdminController::class, 'markWithdrawalPaid']);
        Route::patch('/admin/withdrawals/{id}/reject', [AdminController::class, 'rejectWithdrawal']);
        Route::get('/admin/withdrawal-stats', [AdminController::class, 'withdrawalStats']);
    });

    // Tipster-only routes
    Route::middleware('role:tipster')->group(function () {
        Route::post('/predictions', [PredictionController::class, 'store']);
        
        // Withdrawal routes for tipsters
        Route::get('/withdrawals', [WithdrawalController::class, 'index']);
        Route::post('/withdrawals', [WithdrawalController::class, 'store']);
        Route::get('/withdrawals/{id}', [WithdrawalController::class, 'show']);
        Route::patch('/withdrawals/{id}/cancel', [WithdrawalController::class, 'cancel']);
    });

    // Payment routes
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    
    // Rating management (tipsters can update their own ratings)
    Route::post('/ratings/update/{tipsterId}', [TipsterRatingController::class, 'update']);
    
    // Customer routes
    Route::middleware('role:customer')->group(function () {
        // Subscription routes
        Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::get('/subscriptions/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
        Route::get('/subscriptions/{id}/status', [SubscriptionController::class, 'getStatus']);
        
        // Tipster routes
        Route::get('/tipsters', [TipsterPublicController::class, 'listTipsters']);
        Route::get('/tipsters/{id}/predictions', [TipsterPublicController::class, 'getTipsterPredictions']);
        Route::get('/predictions/{id}', [TipsterPublicController::class, 'showPrediction']);
    });
});

// Predictions (tipster only)
Route::middleware(['auth:sanctum', 'role:tipster'])->group(function () {
    Route::get('/predictions', [PredictionController::class, 'index']);
    Route::post('/predictions', [PredictionController::class, 'store']);
    Route::patch('/predictions/{id}', [PredictionController::class, 'update']);
    Route::post('/predictions/{id}/publish', [PredictionController::class, 'publish']);
    Route::post('/predictions/{id}/lock', [PredictionController::class, 'lock']);
    Route::post('/predictions/{id}/grade', [PredictionController::class, 'grade']);
    Route::post('/predictions/{id}/update-result', [PredictionController::class, 'updateResult']);
    Route::get('/predictions/needing-results', [PredictionController::class, 'getPredictionsNeedingResults']);
    Route::post('/predictions/upload-winning-slip', [PredictionController::class, 'uploadWinningSlip']);
    Route::post('/predictions/upload-image', [PredictionController::class, 'uploadImage']);
});

// Profile routes (authenticated users)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/image', [AuthController::class, 'updateProfileImage']);
    Route::get('/users/{id}/profile-image', [AuthController::class, 'getProfileImage']);
});

// Admin profile image routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::patch('/admin/users/{id}/profile-image', [AdminController::class, 'updateUserProfileImage']);
    Route::get('/admin/users/{id}/profile-image', [AdminController::class, 'getUserProfileImage']);
});
