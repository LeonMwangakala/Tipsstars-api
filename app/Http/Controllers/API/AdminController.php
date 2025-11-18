<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Prediction;
use App\Models\Subscription;
use App\Models\TipsterRating;
use App\Models\CommissionConfig;
use App\Models\WithdrawalRequest;
use App\Models\Booker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone_number' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is admin
        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'phone_number' => ['Access denied. Admin privileges required.'],
            ]);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'Admin login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function dashboard()
    {
        $totalTipsters = User::where('role', 'tipster')->count();
        $activeCustomers = User::where('role', 'customer')->count();
        $predictionsToday = Prediction::whereDate('created_at', Carbon::today())->count();
        
        // Calculate success rate from graded predictions
        $gradedPredictions = Prediction::whereIn('result_status', ['won', 'lost'])->count();
        $wonPredictions = Prediction::where('result_status', 'won')->count();
        $successRate = $gradedPredictions > 0 ? round(($wonPredictions / $gradedPredictions) * 100, 2) : 0;

        // Weekly predictions data (last 7 days)
        $weeklyPredictions = Prediction::where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();

        // Subscription trends (last 30 days)
        $subscriptionTrends = Subscription::where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get();

        // Commission statistics
        $totalCommission = Subscription::getTotalCommission();
        $totalTipsterEarnings = Subscription::where('status', 'active')->sum('tipster_earnings');
        $activeCommissionConfigs = CommissionConfig::where('is_active', true)->count();

        return response()->json([
            'total_tipsters' => $totalTipsters,
            'active_customers' => $activeCustomers,
            'predictions_today' => $predictionsToday,
            'success_rate' => $successRate,
            'total_commission' => $totalCommission,
            'total_tipster_earnings' => $totalTipsterEarnings,
            'active_commission_configs' => $activeCommissionConfigs,
            'weekly_predictions' => $weeklyPredictions,
            'subscription_trends' => $subscriptionTrends,
        ]);
    }

    /**
     * Get all tipsters with filters
     */
    public function tipsters(Request $request)
    {
        $query = User::where('role', 'tipster')->with(['tipsterRating', 'commissionConfig']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Status filter (you can add status field to users table if needed)
        if ($request->has('status') && $request->status !== 'all') {
            // For now, we'll use a placeholder status logic
            // You can implement actual status filtering based on your requirements
        }

        $tipsters = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'data' => $tipsters->items(),
            'pagination' => [
                'current_page' => $tipsters->currentPage(),
                'last_page' => $tipsters->lastPage(),
                'per_page' => $tipsters->perPage(),
                'total' => $tipsters->total(),
            ]
        ]);
    }

    /**
     * Approve tipster
     */
    public function approveTipster(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $tipster = User::where('role', 'tipster')->findOrFail($id);
        $admin = $request->user();

        if (!$tipster->isPending()) {
            return response()->json([
                'message' => 'Only pending tipsters can be approved',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $tipster->update([
                'status' => 'approved',
                'admin_notes' => $request->admin_notes,
            ]);

            DB::commit();

            Log::info('Tipster approved', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'admin_notes' => $request->admin_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipster approved successfully',
                'tipster' => $tipster->load('tipsterRating'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve tipster', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to approve tipster',
            ], 500);
        }
    }

    /**
     * Reject tipster
     */
    public function rejectTipster(Request $request, $id)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        $tipster = User::where('role', 'tipster')->findOrFail($id);
        $admin = $request->user();

        if (!$tipster->isPending()) {
            return response()->json([
                'message' => 'Only pending tipsters can be rejected',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $tipster->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
            ]);

            DB::commit();

            Log::info('Tipster rejected', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'admin_notes' => $request->admin_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipster rejected successfully',
                'tipster' => $tipster->load('tipsterRating'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject tipster', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to reject tipster',
            ], 500);
        }
    }

    /**
     * Get all predictions with filters
     */
    public function predictions(Request $request)
    {
        $query = Prediction::with(['tipster', 'booker']);

        // Tipster filter
        if ($request->has('tipster_id') && $request->tipster_id) {
            $query->where('tipster_id', $request->tipster_id);
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Result status filter
        if ($request->has('result_status') && $request->result_status !== 'all') {
            $query->where('result_status', $request->result_status);
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $predictions = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => $predictions->items(),
            'pagination' => [
                'current_page' => $predictions->currentPage(),
                'last_page' => $predictions->lastPage(),
                'per_page' => $predictions->perPage(),
                'total' => $predictions->total(),
            ]
        ]);
    }

    /**
     * Update prediction
     */
    public function updatePrediction(Request $request, $id)
    {
        $prediction = Prediction::findOrFail($id);

        $validated = $request->validate([
            'booker_id' => 'sometimes|exists:bookers,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'odds_total' => 'sometimes|numeric|min:1',
            'kickoff_at' => 'sometimes|date',
            'kickend_at' => 'nullable|date',
            'confidence_level' => 'sometimes|integer|min:1|max:10',
            'is_premium' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,published,expired',
            'result_status' => 'sometimes|in:pending,won,lost,void,refunded',
            'booking_codes' => 'nullable|array',
            'booking_codes.*' => 'string',
            'betting_slip' => 'nullable|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        // Validate that kickend_at is after kickoff_at if both are provided
        if ($request->has('kickend_at') && $request->has('kickoff_at')) {
            $kickoffAt = new \DateTime($validated['kickoff_at']);
            $kickendAt = new \DateTime($validated['kickend_at']);
            if ($kickendAt <= $kickoffAt) {
                return response()->json([
                    'message' => 'Kickend time must be after kickoff time.',
                ], 422);
            }
        } elseif ($request->has('kickend_at') && $prediction->kickoff_at) {
            $kickoffAt = new \DateTime($prediction->kickoff_at);
            $kickendAt = new \DateTime($validated['kickend_at']);
            if ($kickendAt <= $kickoffAt) {
                return response()->json([
                    'message' => 'Kickend time must be after kickoff time.',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $updateData = [];

            foreach (['booker_id', 'title', 'description', 'odds_total', 'kickoff_at', 'kickend_at', 'confidence_level', 'status', 'result_status'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $validated[$field] ?? null;
                }
            }

            if ($request->has('is_premium')) {
                $updateData['is_premium'] = (bool) $request->is_premium;
            }

            if ($request->has('booking_codes')) {
                $bookingCodes = $request->input('booking_codes');
                $updateData['booking_codes'] = $bookingCodes ? json_encode($bookingCodes) : null;
            }

            if ($request->hasFile('betting_slip')) {
                $bettingSlipPath = $request->file('betting_slip')->store('betting_slips', 'public');
                $relativeUrl = Storage::url($bettingSlipPath);
                $bettingSlipUrl = str_starts_with($relativeUrl, 'http')
                    ? $relativeUrl
                    : url($relativeUrl);

                $updateData['betting_slip_url'] = $bettingSlipUrl;
                $updateData['image_url'] = $bettingSlipUrl;
            }

            if (!empty($updateData)) {
                $prediction->update($updateData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Prediction updated successfully',
                'prediction' => $prediction->fresh()->load('tipster', 'booker'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update prediction', [
                'prediction_id' => $prediction->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update prediction',
            ], 500);
        }
    }

    /**
     * Delete prediction
     */
    public function deletePrediction($id)
    {
        $prediction = Prediction::findOrFail($id);
        $prediction->delete();

        return response()->json([
            'message' => 'Prediction deleted successfully'
        ]);
    }

    /**
     * Get all customers
     */
    public function customers(Request $request)
    {
        $query = User::where('role', 'customer')->with('subscriptions');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(15);

        return response()->json([
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    /**
     * Get all subscriptions
     */
    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['user', 'tipster', 'commissionConfig']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('phone_number', 'like', "%{$search}%");
                })
                ->orWhereHas('tipster', function ($tipsterQuery) use ($search) {
                    $tipsterQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('phone_number', 'like', "%{$search}%");
                });
            });
        }

        // Tipster filter
        if ($request->has('tipster_id') && $request->tipster_id) {
            $query->where('tipster_id', $request->tipster_id);
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => $subscriptions->items(),
            'pagination' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ]
        ]);
    }

    /**
     * Create a subscription for a customer and tipster
     */
    public function createSubscription(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipster_id' => 'required|exists:users,id',
            'plan_type' => 'required|in:weekly,monthly',
            'commission_config_id' => 'nullable|exists:commission_configs,id',
        ]);

        $customer = User::where('role', 'customer')->findOrFail($validated['user_id']);
        $tipster = User::where('role', 'tipster')->findOrFail($validated['tipster_id']);

        if ($customer->id === $tipster->id) {
            return response()->json([
                'message' => 'Customer and tipster must be different users',
            ], 422);
        }

        $existingSubscription = Subscription::where('user_id', $customer->id)
            ->where('tipster_id', $tipster->id)
            ->active()
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'message' => 'Customer already has an active subscription with this tipster',
            ], 409);
        }

        $planType = $validated['plan_type'];
        $price = null;
        $maxLimit = 0;

        if ($planType === 'weekly') {
            $price = $tipster->weekly_subscription_amount;
            $maxLimit = 10000;
        } elseif ($planType === 'monthly') {
            $price = $tipster->monthly_subscription_amount;
            $maxLimit = 40000;
        }

        $numericPrice = is_null($price) ? null : (float) $price;

        if (is_null($numericPrice) || $numericPrice <= 0) {
            return response()->json([
                'message' => 'Selected tipster has not configured a valid price for this plan type',
            ], 422);
        }

        if ($numericPrice > $maxLimit) {
            return response()->json([
                'message' => 'Subscription amount exceeds the allowed maximum for the selected plan',
            ], 422);
        }

        $startAt = Carbon::now();
        $endAt = $planType === 'weekly'
            ? $startAt->copy()->addWeek()
            : $startAt->copy()->addMonth();

        $commissionConfig = null;
        if (!empty($validated['commission_config_id'])) {
            $commissionConfig = CommissionConfig::find($validated['commission_config_id']);
        } elseif ($tipster->commission_config_id) {
            $commissionConfig = CommissionConfig::find($tipster->commission_config_id);
        } else {
            $commissionConfig = CommissionConfig::getDefault();
        }

        $commissionRate = $commissionConfig?->commission_rate ?? 0;
        $commissionConfigId = $commissionConfig?->id;

        try {
            DB::beginTransaction();

            $subscription = Subscription::create([
                'user_id' => $customer->id,
                'tipster_id' => $tipster->id,
                'plan_type' => $planType,
                'price' => $numericPrice,
                'currency' => 'TZS',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'active',
                'commission_rate' => $commissionRate,
                'commission_amount' => 0,
                'tipster_earnings' => 0,
                'commission_config_id' => $commissionConfigId,
            ]);

            $subscription->calculateCommission()->save();

            DB::commit();

            Log::info('Subscription created by admin', [
                'admin_id' => $request->user()->id,
                'subscription_id' => $subscription->id,
                'user_id' => $customer->id,
                'tipster_id' => $tipster->id,
                'plan_type' => $planType,
                'price' => $numericPrice,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'subscription' => $subscription->load(['user', 'tipster', 'commissionConfig']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create subscription by admin', [
                'admin_id' => $request->user()->id,
                'user_id' => $customer->id,
                'tipster_id' => $tipster->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create subscription',
            ], 500);
        }
    }

    /**
     * Send notification
     */
    public function sendNotification(Request $request)
    {
        $request->validate([
            'type' => 'required|in:tipster,customer,all',
            'user_ids' => 'required_if:type,tipster,customer|array',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Add notification logic here
        // You might want to create a notifications table and send push notifications

        return response()->json([
            'message' => 'Notification sent successfully'
        ]);
    }

    /**
     * Get all admin users
     */
    public function adminUsers(Request $request)
    {
        $query = User::where('role', 'admin');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Status filter (you can add is_active field to users table)
        if ($request->has('status') && $request->status !== 'all') {
            // For now, we'll use a placeholder status logic
            // You can implement actual status filtering based on your requirements
        }

        $admins = $query->paginate(15);

        return response()->json([
            'data' => $admins->items(),
            'pagination' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
            ]
        ]);
    }

    /**
     * Create new admin user
     */
    public function createAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email|unique:users,email',
        ]);

        $admin = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'Admin user created successfully',
            'admin' => $admin
        ], 201);
    }

    /**
     * Update admin user
     */
    public function updateAdmin(Request $request, $id)
    {
        $admin = User::where('role', 'admin')->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
        ]);

        $updateData = $request->only(['name', 'phone_number', 'email']);
        
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $admin->update($updateData);

        return response()->json([
            'message' => 'Admin user updated successfully',
            'admin' => $admin
        ]);
    }

    /**
     * Delete admin user
     */
    public function deleteAdmin($id)
    {
        $admin = User::where('role', 'admin')->findOrFail($id);
        
        // Prevent deleting the current user
        if ($admin->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account'
            ], 400);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin user deleted successfully'
        ]);
    }

    /**
     * Toggle admin status (activate/deactivate)
     */
    public function toggleAdminStatus($id)
    {
        $admin = User::where('role', 'admin')->findOrFail($id);
        
        // Prevent deactivating the current user
        if ($admin->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot deactivate your own account'
            ], 400);
        }

        // You can add an is_active field to users table
        // For now, we'll just return success
        $status = 'activated'; // or 'deactivated' based on your logic

        return response()->json([
            'message' => "Admin user {$status} successfully",
            'admin' => $admin
        ]);
    }

    /**
     * Get all commission configurations
     */
    public function commissionConfigs(Request $request)
    {
        $query = CommissionConfig::query();

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $configs = $query->paginate(15);

        return response()->json([
            'data' => $configs->items(),
            'pagination' => [
                'current_page' => $configs->currentPage(),
                'last_page' => $configs->lastPage(),
                'per_page' => $configs->perPage(),
                'total' => $configs->total(),
            ]
        ]);
    }

    /**
     * Create new commission configuration
     */
    public function createCommissionConfig(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:commission_configs,name',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $config = CommissionConfig::create([
            'name' => $request->name,
            'commission_rate' => $request->commission_rate,
            'description' => $request->description,
            'is_active' => $request->get('is_active', true),
        ]);

        return response()->json([
            'message' => 'Commission configuration created successfully',
            'config' => $config
        ], 201);
    }

    /**
     * Update commission configuration
     */
    public function updateCommissionConfig(Request $request, $id)
    {
        $config = CommissionConfig::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:commission_configs,name,' . $id,
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $config->update($request->all());

        return response()->json([
            'message' => 'Commission configuration updated successfully',
            'config' => $config
        ]);
    }

    /**
     * Delete commission configuration
     */
    public function deleteCommissionConfig($id)
    {
        $config = CommissionConfig::findOrFail($id);
        
        // Check if config is being used by any subscriptions
        if ($config->subscriptions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete commission configuration that is being used by subscriptions'
            ], 400);
        }

        $config->delete();

        return response()->json([
            'message' => 'Commission configuration deleted successfully'
        ]);
    }

    /**
     * Get commission statistics
     */
    public function commissionStats()
    {
        $totalCommission = Subscription::getTotalCommission();
        $activeConfigs = CommissionConfig::getActive();
        $defaultConfig = CommissionConfig::getDefault();
        
        // Get top earning tipsters
        $topTipsters = User::where('role', 'tipster')
            ->withSum('subscriptions as total_earnings', 'tipster_earnings')
            ->orderByDesc('total_earnings')
            ->limit(10)
            ->get(['id', 'name', 'phone_number']);

        return response()->json([
            'total_commission' => $totalCommission,
            'active_configs_count' => $activeConfigs->count(),
            'default_config' => $defaultConfig,
            'top_earning_tipsters' => $topTipsters,
        ]);
    }

    /**
     * Get all withdrawal requests with filters
     */
    public function withdrawalRequests(Request $request)
    {
        $query = WithdrawalRequest::with(['tipster:id,name,phone_number', 'admin:id,name']);

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['pending', 'paid', 'rejected', 'cancelled'])) {
            $query->where('status', $request->status);
        }

        // Filter by tipster
        if ($request->has('tipster_id')) {
            $query->where('tipster_id', $request->tipster_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by tipster name or phone
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('tipster', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderBy('created_at', 'desc')->paginate(15);

        // Add summary statistics
        $summary = [
            'total_pending' => WithdrawalRequest::where('status', 'pending')->count(),
            'total_paid' => WithdrawalRequest::where('status', 'paid')->count(),
            'total_rejected' => WithdrawalRequest::where('status', 'rejected')->count(),
            'total_amount_pending' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
            'total_amount_paid' => WithdrawalRequest::where('status', 'paid')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
            'summary' => $summary,
        ]);
    }

    /**
     * Get withdrawal request details
     */
    public function withdrawalRequest($id)
    {
        $withdrawal = WithdrawalRequest::with(['tipster:id,name,phone_number,role', 'admin:id,name'])
            ->findOrFail($id);

        // Get tipster's earnings summary
        $tipster = $withdrawal->tipster;
        $earningsSummary = [
            'total_earnings' => $tipster->getTotalEarnings(),
            'available_balance' => $tipster->getAvailableBalance(),
            'total_withdrawals' => $tipster->withdrawalRequests()->where('status', 'paid')->sum('amount'),
            'pending_withdrawals' => $tipster->withdrawalRequests()->where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $withdrawal,
            'tipster_earnings' => $earningsSummary,
        ]);
    }

    /**
     * Mark withdrawal request as paid
     */
    public function markWithdrawalPaid(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $withdrawal = WithdrawalRequest::findOrFail($id);
        $admin = $request->user();

        if (!$withdrawal->isPending()) {
            return response()->json([
                'message' => 'Only pending withdrawal requests can be marked as paid',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $withdrawal->markAsPaid($admin->id, $request->notes);

            DB::commit();

            Log::info('Withdrawal request marked as paid', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $withdrawal->id,
                'tipster_id' => $withdrawal->tipster_id,
                'amount' => $withdrawal->amount,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request marked as paid successfully',
                'data' => $withdrawal->load('tipster', 'admin'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark withdrawal as paid', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to mark withdrawal as paid',
            ], 500);
        }
    }

    /**
     * Reject withdrawal request
     */
    public function rejectWithdrawal(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $withdrawal = WithdrawalRequest::findOrFail($id);
        $admin = $request->user();

        if (!$withdrawal->isPending()) {
            return response()->json([
                'message' => 'Only pending withdrawal requests can be rejected',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $withdrawal->markAsRejected($admin->id, $request->notes);

            DB::commit();

            Log::info('Withdrawal request rejected', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $withdrawal->id,
                'tipster_id' => $withdrawal->tipster_id,
                'amount' => $withdrawal->amount,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request rejected successfully',
                'data' => $withdrawal->load('tipster', 'admin'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject withdrawal', [
                'admin_id' => $admin->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to reject withdrawal request',
            ], 500);
        }
    }

    /**
     * Get withdrawal statistics
     */
    public function withdrawalStats()
    {
        $stats = [
            'total_requests' => WithdrawalRequest::count(),
            'pending_requests' => WithdrawalRequest::where('status', 'pending')->count(),
            'paid_requests' => WithdrawalRequest::where('status', 'paid')->count(),
            'rejected_requests' => WithdrawalRequest::where('status', 'rejected')->count(),
            'cancelled_requests' => WithdrawalRequest::where('status', 'cancelled')->count(),
            'total_amount_requested' => WithdrawalRequest::sum('amount'),
            'total_amount_paid' => WithdrawalRequest::where('status', 'paid')->sum('amount'),
            'total_amount_pending' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
        ];

        // Monthly withdrawal trends (last 12 months)
        $monthlyTrends = WithdrawalRequest::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('EXTRACT(YEAR FROM created_at) as year, EXTRACT(MONTH FROM created_at) as month, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Top tipsters by withdrawal amount
        $topTipsters = WithdrawalRequest::with('tipster:id,name,phone_number')
            ->selectRaw('tipster_id, COUNT(*) as request_count, SUM(amount) as total_amount')
            ->groupBy('tipster_id')
            ->orderBy('total_amount', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'monthly_trends' => $monthlyTrends,
            'top_tipsters' => $topTipsters,
        ]);
    }

    /**
     * Update subscription status
     */
    public function updateSubscriptionStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,expired,cancelled',
        ]);

        $subscription = Subscription::findOrFail($id);
        $admin = $request->user();
        $oldStatus = $subscription->status;
        $newStatus = $request->status;

        try {
            DB::beginTransaction();

            $subscription->update([
                'status' => $newStatus,
            ]);

            DB::commit();

            Log::info('Subscription status updated', [
                'admin_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'tipster_id' => $subscription->tipster_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription status updated successfully',
                'subscription' => $subscription->load('user', 'tipster'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update subscription status', [
                'admin_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update subscription status',
            ], 500);
        }
    }

    /**
     * Register new customer or tipster
     */
    public function registerUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:6',
            'role' => 'required|in:customer,tipster',
            'id_document' => 'required_if:role,tipster|string',
            'weekly_subscription_amount' => 'nullable|numeric|min:0|max:10000',
            'monthly_subscription_amount' => 'nullable|numeric|min:0|max:40000',
        ]);

        $admin = $request->user();

        try {
            DB::beginTransaction();

            $userData = [
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'status' => $validated['role'] === 'customer' ? 'approved' : 'pending',
            ];

            if ($validated['role'] === 'tipster' && !empty($validated['id_document'])) {
                $userData['id_document'] = $validated['id_document'];
            }

            if ($validated['role'] === 'tipster') {
                $userData['weekly_subscription_amount'] = $validated['weekly_subscription_amount'] ?? null;
                $userData['monthly_subscription_amount'] = $validated['monthly_subscription_amount'] ?? null;
            }

            $user = User::create($userData);

            DB::commit();

            Log::info('User registered by admin', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'role' => $user->role,
                'status' => $user->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($user->role) . ' registered successfully',
                'user' => $user->load('tipsterRating'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to register user', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to register user',
            ], 500);
        }
    }

    /**
     * Get tipster ID document
     */
    public function getTipsterIdDocument($id)
    {
        $tipster = User::where('role', 'tipster')->findOrFail($id);
        
        if (!$tipster->hasIdDocument()) {
            return response()->json([
                'message' => 'No ID document found for this tipster',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'id_document' => $tipster->id_document,
        ]);
    }

    /**
     * Update tipster
     */
    public function updateTipster(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number,' . $id,
            'commission_config_id' => 'nullable|exists:commission_configs,id',
            'weekly_subscription_amount' => 'nullable|numeric|min:0|max:10000',
            'monthly_subscription_amount' => 'nullable|numeric|min:0|max:40000',
        ]);

        $tipster = User::where('role', 'tipster')->findOrFail($id);
        $admin = $request->user();

        try {
            DB::beginTransaction();

            $updateData = [
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'],
            ];

            if (array_key_exists('commission_config_id', $validated)) {
                $updateData['commission_config_id'] = $validated['commission_config_id'];
            }

            if (array_key_exists('weekly_subscription_amount', $validated)) {
                $updateData['weekly_subscription_amount'] = $validated['weekly_subscription_amount'];
            }

            if (array_key_exists('monthly_subscription_amount', $validated)) {
                $updateData['monthly_subscription_amount'] = $validated['monthly_subscription_amount'];
            }

            $tipster->update($updateData);

            DB::commit();

            Log::info('Tipster updated', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'old_name' => $tipster->getOriginal('name'),
                'new_name' => $request->name,
                'old_phone' => $tipster->getOriginal('phone_number'),
                'new_phone' => $request->phone_number,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipster updated successfully',
                'tipster' => $tipster->load('tipsterRating', 'commissionConfig'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update tipster', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update tipster',
            ], 500);
        }
    }

    /**
     * Create prediction for tipster (admin only)
     */
    public function createPrediction(Request $request)
    {
        $validated = $request->validate([
            'tipster_id' => 'required|exists:users,id',
            'booker_id' => 'required|exists:bookers,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'odds_total' => 'required|numeric|min:1',
            'kickoff_at' => 'required|date|after:now',
            'kickend_at' => 'nullable|date|after:kickoff_at',
            'confidence_level' => 'required|integer|min:1|max:10',
            'is_premium' => 'boolean',
            'status' => 'in:draft,published',
            'result_status' => 'in:pending,won,lost,void',
            'booking_codes' => 'nullable|array',
            'booking_codes.*' => 'string',
            'betting_slip' => 'nullable|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $admin = $request->user();
        $tipster = User::where('role', 'tipster')->findOrFail($validated['tipster_id']);

        // Check if tipster is approved
        if (!$tipster->isApproved()) {
            return response()->json([
                'message' => 'Cannot create prediction for unapproved tipster',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $bettingSlipUrl = null;
            if ($request->hasFile('betting_slip')) {
                $bettingSlipPath = $request->file('betting_slip')->store('betting_slips', 'public');
                $relativeUrl = Storage::url($bettingSlipPath);
                $bettingSlipUrl = str_starts_with($relativeUrl, 'http')
                    ? $relativeUrl
                    : url($relativeUrl);
            }

            $prediction = Prediction::create([
                'tipster_id' => $validated['tipster_id'],
                'booker_id' => $validated['booker_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'odds_total' => $validated['odds_total'],
                'kickoff_at' => $validated['kickoff_at'],
                'kickend_at' => $validated['kickend_at'] ?? null,
                'confidence_level' => $validated['confidence_level'],
                'is_premium' => $request->boolean('is_premium', false),
                'status' => $request->get('status', 'draft'),
                'result_status' => $request->get('result_status', 'pending'),
                'booking_codes' => isset($validated['booking_codes']) ? json_encode($validated['booking_codes']) : null,
                'betting_slip_url' => $bettingSlipUrl,
                'image_url' => $bettingSlipUrl ?? '',
                'created_by_admin' => true,
                'admin_id' => $admin->id,
            ]);

            DB::commit();

            Log::info('Prediction created by admin', [
                'admin_id' => $admin->id,
                'tipster_id' => $tipster->id,
                'prediction_id' => $prediction->id,
                'title' => $prediction->title,
                'status' => $prediction->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prediction created successfully',
                'prediction' => $prediction->load('tipster', 'booker'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prediction', [
                'admin_id' => $admin->id,
                'tipster_id' => $validated['tipster_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create prediction',
            ], 500);
        }
    }

    /**
     * List bookers with optional filters
     */
    public function bookers(Request $request)
    {
        $query = Booker::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $query->orderBy('name');

        if ($request->boolean('simple', false)) {
            return response()->json([
                'data' => $query->get(),
            ]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $bookers = $query->paginate($perPage);

        return response()->json([
            'data' => $bookers->items(),
            'pagination' => [
                'current_page' => $bookers->currentPage(),
                'last_page' => $bookers->lastPage(),
                'per_page' => $bookers->perPage(),
                'total' => $bookers->total(),
            ],
        ]);
    }

    /**
     * Create a new booker
     */
    public function createBooker(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bookers,name',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $booker = Booker::create([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booker created successfully',
            'booker' => $booker,
        ], 201);
    }

    /**
     * Update an existing booker
     */
    public function updateBooker(Request $request, $id)
    {
        $booker = Booker::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bookers,name,' . $booker->id,
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $booker->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', $booker->is_active),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booker updated successfully',
            'booker' => $booker,
        ]);
    }

    /**
     * Delete a booker
     */
    public function deleteBooker($id)
    {
        $booker = Booker::findOrFail($id);

        $hasPredictions = Prediction::where('booker_id', $booker->id)->exists();
        if ($hasPredictions) {
            return response()->json([
                'message' => 'Cannot delete a booker that is associated with predictions',
            ], 400);
        }

        $booker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booker deleted successfully',
        ]);
    }
}