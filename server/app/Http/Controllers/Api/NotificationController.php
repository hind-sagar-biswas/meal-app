<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\BroadcastNotificationRequest;
use App\Models\User;
use App\Notifications\GenericMessNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    /**
     * Get paginated notifications inbox and unread count for current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 50);

        $notifications = $user->notifications()->latest()
            ->paginate($perPage);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Broadcast an announcement notification to all active mess members.
     */
    public function broadcast(BroadcastNotificationRequest $request): JsonResponse
    {
        $sender = $request->user();
        $activeMembers = User::where('is_active', true)->get();

        $notification = new GenericMessNotification(
            title: $request->input('title'),
            message: $request->input('message'),
            sender: $sender,
            note: $request->input('note'),
            severity: $request->input('severity', 'info')
        );

        Notification::send($activeMembers, $notification);

        return response()->json([
            'message' => 'Announcement broadcast successfully.',
        ]);
    }
}
