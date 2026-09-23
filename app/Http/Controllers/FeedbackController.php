<?php

namespace App\Http\Controllers;

use App\Models\FeedbackTicket;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = FeedbackTicket::query()
            ->with('user')
            ->latest();

        if (! $user->hasElevatedAccess()) {
            $query->where('user_id', $user->id);
        }

        $tickets = $query->paginate(20);

        return view('feedback.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        $prefillOrderId = (int) $request->query('order_id', 0);
        $prefillSubject = (string) $request->query('subject', '');
        $prefillMessage = (string) $request->query('message', '');

        if ($prefillOrderId > 0 && $prefillMessage === '') {
            $prefillMessage = 'Заявка №'.$prefillOrderId.': ';
        }

        return view('feedback.create', compact('prefillOrderId', 'prefillSubject', 'prefillMessage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',
                File::types([
                    'png',
                    'jpg',
                    'jpeg',
                    'gif',
                    'doc',
                    'txt',
                    'docx',
                    'xls',
                    'xlsx',
                    'pdf',
                ]),
            ],
        ], [
            'subject.required' => 'Выберите тему обращения.',
            'message.required' => 'Введите сообщение.',
            'message.max' => 'Сообщение не должно быть длиннее 1000 символов.',
            'attachments.max' => 'Можно приложить максимум 10 файлов.',
            'attachments.*.max' => 'Размер каждого файла не должен превышать 10 МБ.',
        ]);

        $attachments = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = [
                    'path' => $file->store('feedback-attachments', 'local'),
                    'disk' => 'local',
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $ticket = FeedbackTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'new',
            'screenshot_path' => null,
            'attachments' => $attachments,
        ]);

        $admins = User::query()->portalAdmins()->get();

        foreach ($admins as $admin) {
            PortalNotification::create([
                'user_id' => $admin->id,
                'title' => 'Новое обращение',
                'message' => 'Поступило новое обращение #'.$ticket->id.' от '.$request->user()->name,
                'type' => 'feedback',
                'url' => route('feedback.show', $ticket),
            ]);
        }

        return redirect()
            ->route('feedback.index')
            ->with('success', 'Обращение отправлено.');
    }

    public function show(Request $request, FeedbackTicket $feedback)
    {
        $user = $request->user();

        if (! $user->hasElevatedAccess() && $feedback->user_id !== $user->id) {
            abort(403);
        }

        $feedback->load('user');

        return view('feedback.show', [
            'ticket' => $feedback,
        ]);
    }
}