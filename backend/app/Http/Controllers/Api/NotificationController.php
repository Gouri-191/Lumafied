<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->institution_id) {
                $q->orWhere('institution_id', $user->institution_id);
            }
        });

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }

    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = Notification::findOrFail($id);

        if ($notification->user_id && $notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->institution_id) {
                $q->orWhere('institution_id', $user->institution_id);
            }
        })->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
