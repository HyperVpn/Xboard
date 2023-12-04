<?php

namespace App\Http\Controllers\V1\Staff;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $ticket = Ticket::where('id', $request->input('id'))
                ->first();
            if (!$ticket) {
                throw new ApiException(500, '工单不存在');
            }
            $ticket['message'] = TicketMessage::where('ticket_id', $ticket->id)->get();
            for ($i = 0; $i < count($ticket['message']); $i++) {
                if ($ticket['message'][$i]['user_id'] !== $ticket->user_id) {
                    $ticket['message'][$i]['is_me'] = true;
                } else {
                    $ticket['message'][$i]['is_me'] = false;
                }
            }
            return response([
                'data' => $ticket
            ]);
        }
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $model = Ticket::orderBy('created_at', 'DESC');
        if ($request->input('status') !== NULL) {
            $model->where('status', $request->input('status'));
        }
        $total = $model->count();
        $res = $model->forPage($current, $pageSize)
            ->get();
        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    public function reply(Request $request)
    {
        if (empty($request->input('id'))) {
            throw new ApiException(422, '参数错误');
        }
        if (empty($request->input('message'))) {
            throw new ApiException(500, '消息不能为空');
        }
        $ticketService = new TicketService();
        $ticketService->replyByAdmin(
            $request->input('id'),
            $request->input('message'),
            $request->user['id']
        );
        return response([
            'data' => true
        ]);
    }

    public function close(Request $request)
    {
        if (empty($request->input('id'))) {
            throw new ApiException(422, '参数错误');
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->first();
        if (!$ticket) {
            throw new ApiException(500, '工单不存在');
        }
        $ticket->status = 1;
        if (!$ticket->save()) {
            throw new ApiException(500, '关闭失败');
        }
        return response([
            'data' => true
        ]);
    }
}
