<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CoachApplication;
use App\Mail\CoachApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * Submit coach application (after registration)
     */
    public function submit(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'experience' => 'required|string',
        //     'specialties' => 'required|string',
        //     'reason' => 'required|string|min:50',
        //     'certification' => 'nullable|string',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        $user = $request->user();

        // Check if user already has an application
        $existingApplication = CoachApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a ' . $existingApplication->status . ' application'
            ], 400);
        }

        // Create application
        $application = CoachApplication::create([
            'user_id' => $user->id,
            'experience' => $request->experience,
            'specialties' => $request->specialties,
            'reason' => $request->reason,
            'certification' => $request->certification,
            'status' => 'pending'
        ]);

        // Send email notification to admin (you'll need to implement this)
        // $this->notifyAdmins($application);

        // Send confirmation email to coach
        try {
            Mail::to($user->email)->send(new CoachApplicationReceived($user));
        } catch (\Exception $e) {
            \Log::error('Failed to send application received email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'application' => $application
        ], 201);
    }

    /**
     * Get application status for current user
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        $application = CoachApplication::where('user_id', $user->id)->latest()->first();

        return response()->json([
            'success' => true,
            'has_applied' => !is_null($application),
            'application' => $application,
            'is_approved' => $user->is_approved
        ]);
    }

    /**
     * Admin: Get all pending applications
     */
    public function getPendingApplications()
    {
        $applications = CoachApplication::with('user')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications
        ]);
    }

    /**
     * Admin: Approve application
     */
    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $application = CoachApplication::with('user')->findOrFail($id);
        
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This application has already been ' . $application->status
            ], 400);
        }

        $application->update([
            'status' => 'approved',
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id
        ]);

        // Update user approval status
        $user = $application->user;
        $user->update([
            'is_approved' => true,
            'approved_at' => now()
        ]);

        // Send approval email (you'll need to create this)
        // Mail::to($user->email)->send(new CoachApplicationApproved($user));

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully',
            'application' => $application->load('user')
        ]);
    }
}