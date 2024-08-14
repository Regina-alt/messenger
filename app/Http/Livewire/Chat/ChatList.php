<?php

namespace App\Http\Livewire\Chat;

use App\Models\Conversation;
use Livewire\Component;

class ChatList extends Component
{
    public $selectedConversation;
    public $query;


    protected $listeners=['refresh'=>'$refresh', 'deleteConversation'];

    public function deleteByUser($id, $deleteFor)
    {
        $userId = auth()->id();

        $conversation = Conversation::find(decrypt($id));

        if (!$conversation) {
            abort(404, 'Conversation not found');
        }

        if ($deleteFor === 'both') {
            // Удалить все сообщения чата
            $conversation->messages()->delete();
            // Затем окончательно удалить чат
            $conversation->forceDelete();
        } else {
            $conversation->messages()->each(function($message) use($userId){

                if($message->sender_id===$userId){

                    $message->update(['sender_deleted_at'=>now()]);
                }
                elseif($message->receiver_id===$userId){

                    $message->update(['receiver_deleted_at'=>now()]);
                }


            } );

            $receiverAlsoDeleted =$conversation->messages()
                ->where(function ($query) use($userId){

                    $query->where('sender_id',$userId)
                        ->orWhere('receiver_id',$userId);

                })->where(function ($query) use($userId){

                    $query->whereNull('sender_deleted_at')
                        ->orWhereNull('receiver_deleted_at');

                })->doesntExist();

            if ($receiverAlsoDeleted) {

                $conversation->forceDelete();

            }
        }

        return redirect(route('chat.index'));
    }

    public function deleteConversation($id, $deleteFor)
    {

        $this->deleteByUser($id, $deleteFor);
    }

    public function render()
    {
        $user = auth()->user();
        return view('livewire.chat.chat-list', [
            'conversations' => $user->conversations()->latest('updated_at')->get()
        ]);
    }
}
