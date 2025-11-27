<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;

class ChatController extends Controller
{
    public function index()
    {
        $messages = Message::orderBy('created_at', 'desc')->take(100)->get();
        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request)
    {
        $request->validate(['body' => 'required|string|max:2000', 'author_name' => 'nullable|string|max:255']);

        $message = Message::create([
            'user_id' => $request->user() ? $request->user()->id : null,
            'author_name' => $request->input('author_name', null),
            'body' => $request->input('body'),
        ]);

        return response()->json(['message' => $message], 201);
    }
}
