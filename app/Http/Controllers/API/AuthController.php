<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user with password
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'sometimes|in:customer,tipster,admin'
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'password' => $request->password,
            'role' => $request->role ?? 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login with password (Admin only)
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
                'phone_number' => ['Access denied. Only admin users can login to this platform.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Send OTP to phone number
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['phone_number' => 'required']);

        $code = rand(1000, 9999);
        OtpCode::create([
            'phone_number' => $request->phone_number,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
        ]);

        // TODO: Integrate SMS provider like Beem to send OTP
        // For development, we'll log the OTP
        \Log::info('OTP Code for ' . $request->phone_number . ': ' . $code);

        return response()->json(['message' => 'OTP sent']);
    }

    /**
     * Verify OTP and login/register user
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required',
            'code' => 'required',
            'name' => 'sometimes|string|max:255', // Optional name for new users
        ]);

        // Test mode: Accept test OTP code (1234) in development
        $isTestMode = env('APP_ENV') === 'local' || env('APP_DEBUG') === true;
        $testOtpCode = '1234';
        
        if ($isTestMode && $request->code === $testOtpCode) {
            // Test mode: Create OTP record if it doesn't exist for test code
            $otp = OtpCode::firstOrCreate(
                [
                    'phone_number' => $request->phone_number,
                    'code' => $testOtpCode,
                ],
                [
                    'expires_at' => now()->addMinutes(60), // Extended expiry for test
                ]
            );
        } else {
            // Normal OTP verification
            $otp = OtpCode::where('phone_number', $request->phone_number)
                          ->where('code', $request->code)
                          ->where('expires_at', '>', now())
                          ->latest()->first();
        }

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $user = User::firstOrCreate(
            ['phone_number' => $request->phone_number],
            [
                'name' => $request->name ?? 'User', // Use provided name or default
                'role' => 'customer'
            ]
        );

        // Delete used OTP
        $otp->delete();

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'OTP verification successful',
            'token' => $token, 
            'user' => $user
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Update user profile image
     */
    public function updateProfileImage(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|string', // Base64 encoded image
        ]);

        $user = auth()->user();
        
        // Validate base64 image
        $base64 = $request->profile_image;
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $base64)) {
            return response()->json([
                'message' => 'Invalid image format. Please upload a valid image file.'
            ], 400);
        }

        // Check file size (max 2MB)
        $imageData = base64_decode(preg_replace('/^data:image\/(jpeg|jpg|png|gif);base64,/', '', $base64));
        if (strlen($imageData) > 2 * 1024 * 1024) { // 2MB limit
            return response()->json([
                'message' => 'Image size too large. Maximum size is 2MB.'
            ], 400);
        }

        $user->update([
            'profile_image' => $base64
        ]);

        return response()->json([
            'message' => 'Profile image updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Get user profile image
     */
    public function getProfileImage($userId)
    {
        $user = User::findOrFail($userId);
        
        if (!$user->hasProfileImage()) {
            return response()->json([
                'message' => 'No profile image found'
            ], 404);
        }

        return response()->json([
            'profile_image' => $user->profile_image
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
        ]);

        $updateData = $request->only(['name', 'phone_number', 'email']);
        
        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Register as tipster with ID document
     */
    public function registerTipster(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'id_document' => 'required|string', // Base64 encoded image
            'id_type' => 'required|in:nida,driving_license,voters_id',
        ]);

        // Validate base64 image
        $base64 = $request->id_document;
        if (!preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $base64)) {
            return response()->json([
                'message' => 'Invalid image format. Please upload a valid image file.'
            ], 400);
        }

        // Check file size (max 5MB for ID documents)
        $imageData = base64_decode(preg_replace('/^data:image\/(jpeg|jpg|png|gif);base64,/', '', $base64));
        if (strlen($imageData) > 5 * 1024 * 1024) { // 5MB limit
            return response()->json([
                'message' => 'Image size too large. Maximum size is 5MB.'
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'role' => 'tipster',
            'id_document' => $base64,
            'status' => 'pending', // Tipsters need approval
        ]);

        return response()->json([
            'message' => 'Tipster registration submitted successfully. Please wait for admin approval.',
            'user' => $user,
            'status' => 'pending',
        ], 201);
    }
}
