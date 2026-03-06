<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConnectionRequest;

class ConnectionRequestController extends Controller
{
    public function index()
    {
        return ConnectionRequest::latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'coach_id' => 'required|exists:coaches,id',
            'client_goal' => 'nullable|array',
            'client_bio' => 'nullable|string',
            'request_type' => 'required|in:instant,scheduled',
            'scheduled_time' => 'nullable|date'
        ]);

        $data['client_id'] = auth()->id();

        $connection = ConnectionRequest::create($data);

        return response()->json([
            'message' => 'Connection request created',
            'data' => $connection
        ], 201);
    }

    public function show($id)
    {
        return ConnectionRequest::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $connection = ConnectionRequest::findOrFail($id);

        $connection->update($request->all());

        return response()->json($connection);
    }

    public function destroy($id)
    {
        $connection = ConnectionRequest::findOrFail($id);
        $connection->delete();

        return response()->json([
            'message' => 'Connection request deleted'
        ]);
    }
}