<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Core\UserRole;
use App\Models\Coach\Coach;
use App\Models\Coach\PendingCoachApplication;
use App\Mail\CoachInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Api\BaseController;

class AdminController extends BaseController
{
    public function users()
    {
        return $this->success(User::latest()->paginate(20));
    }

    /*
    |--------------------------------------------------------------------------
    | Approved Coaches
    |--------------------------------------------------------------------------
    */

    public function coaches()
{
    try {

        $coaches = Coach::latest()->paginate(20);

        return response()->json([
            'coaches' => $coaches->items(),
            'pagination' => [
                'current_page' => $coaches->currentPage(),
                'per_page' => $coaches->perPage(),
                'total' => $coaches->total(),
                'last_page' => $coaches->lastPage(),
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

    /*
    |--------------------------------------------------------------------------
    | Pending Coach Applications
    |--------------------------------------------------------------------------
    */

    public function pendingApplications()
    {
        return $this->success(
            PendingCoachApplication::where('status', 'pending')
                ->latest()
                ->paginate(20)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approve Coach Application
    |--------------------------------------------------------------------------
    */

   public function approveApplication($id)
{
    $application = PendingCoachApplication::findOrFail($id);

    DB::beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Create or get existing user
        |--------------------------------------------------------------------------
        */

        
        $user = User::firstOrCreate(
            ['email' => $application->email],
            [
                'name' => $application->name,
                'password' => Hash::make(Str::random(16)) // temporary password
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Assign coach role
        |--------------------------------------------------------------------------
        */

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role' => 'coach'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Create coach profile
        |--------------------------------------------------------------------------
        */
        $coach = Coach::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $application->name,
                'email' => $application->email,
                'title' => $application->title ?? 'Coach',
                'bio' => $application->bio ?? 'Coach profile will be updated soon.',
                'years_experience' => $application->years_experience ?? 0,
                'specialties' => $application->specialties ?? [],
                'similar_experiences' => [],
                'rating' => 0,
                'total_reviews' => 0,
                'availability_hours' => null,
                'timezone' => 'UTC',
                'social_links' => [],
                'is_active' => true,
                'available_now' => false,
                'notification_email' => $application->email,
                'notification_phone' => null,
                'coaching_expertise' => null,
                'coaching_style' => null,
                'client_challenge_example' => null,
                'personal_experiences' => null,
                'hourly_rate_amount' => 0,
                'hourly_rate_currency' => 'USD',
                'hourly_coin_cost' => 0,
                'booking_buffer_minutes' => 0,
                'max_session_duration' => 60,
                'min_session_duration' => 30,
                'immediate_availability' => false,
                'response_preference_minutes' => 60
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Mark application approved
        |--------------------------------------------------------------------------
        */

        $application->update([
            'status' => 'approved'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Generate password setup token
        |--------------------------------------------------------------------------
        */

        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => bcrypt($token),
                'created_at' => now()
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Send invitation email
        |--------------------------------------------------------------------------
        */

        Mail::to($user->email)->send(
            new CoachInvitationMail($user->email, $token)
        );

        DB::commit();

        return $this->success([
            'message' => 'Coach approved and invitation email sent.',
            'coach' => $coach
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'message' => 'Failed to approve coach',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /*
    |--------------------------------------------------------------------------
    | Sessions
    |--------------------------------------------------------------------------
    */

    public function sessions()
    {
        return $this->success(CoachingSession::latest()->paginate(20));
    }
}