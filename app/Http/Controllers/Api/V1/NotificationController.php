<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /**
     * GET /notifications
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return $this->paginated($notifications, NotificationResource::class);
    }

    /**
     * GET /notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['count' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * PATCH /notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return $this->error('Notification not found.', 404, 'not_found');
        }

        $notification->markAsRead();

        return $this->message('Notification marked as read.');
    }

    /**
     * POST /notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->message('All notifications marked as read.');
    }
}
