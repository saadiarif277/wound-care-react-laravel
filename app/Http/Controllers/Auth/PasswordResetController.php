<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailNotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    protected EmailNotificationService $emailService;
    
    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }
    
    /**
     * Show the forgot password form
     */
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }
    
    /**
     * Handle forgot password request
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);
        
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->withErrors(['email' => 'We could not find a user with that email address.']);
        }
        
        // Generate token
        $token = Str::random(64);
        
        // Delete any existing tokens for this email
        DB::table('password_resets')->where('email', $user->email)->delete();
        
        // Store token in password_resets table
        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => bcrypt($token),
            'created_at' => now(),
        ]);
        
        // Send email using notification service
        $this->emailService->sendPasswordResetNotification($user, $token);
        
        return back()->with('status', 'We have emailed your password reset link!');
    }
    
    /**
     * Show the reset password form
     */
    public function reset(Request $request, $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->email
        ]);
    }
    
    /**
     * Handle password reset
     */
    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        
        // Find the password reset record
        $resetRecord = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();
            
        if (!$resetRecord || !password_verify($request->token, $resetRecord->token)) {
            return back()->withErrors(['email' => 'Invalid or expired password reset token.']);
        }
        
        // Check if token is expired (60 minutes)
        if (now()->subMinutes(60)->greaterThan($resetRecord->created_at)) {
            return back()->withErrors(['email' => 'Password reset token has expired.']);
        }
        
        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => bcrypt($request->password)
        ]);
        
        // Delete the password reset record
        DB::table('password_resets')->where('email', $request->email)->delete();
        
        // Send confirmation email
        $this->emailService->sendPasswordResetConfirmation($user);
        
        return redirect()->route('login')->with('status', 'Your password has been reset successfully!');
    }
}
