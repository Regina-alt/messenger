<?php

namespace App\Http\Livewire\Chat;

use App\Models\Message;
use App\Notifications\MessageRead;
use App\Notifications\MessageSent;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatBox extends Component
{
    use WithFileUploads;

    public $selectedConversation;
    public $loadedMessages;
    public $body = '';
    public $files = [];

    public $paginate_var = 10;

    protected $listeners = [
        'loadMore'
    ];

    public function getListeners()
    {
        $auth_id = auth()->user()->id;

        return [
            'loadMore',
            "echo-private:users.{$auth_id},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated" => 'broadcastedNotifications'
        ];

    }

    public function broadcastedNotifications($event)
    {


        if ($event['type'] == MessageSent::class) {

            if ($event['conversation_id'] == $this->selectedConversation->id) {

                $this->dispatchBrowserEvent('scroll-bottom');

                $newMessage = Message::find($event['message_id']);


                #push message
                $this->loadedMessages->push($newMessage);

                $newMessage->read_at = now();
                $newMessage->save();

                $this->selectedConversation->getReceiver()->notify(new MessageRead($this->selectedConversation->id));
            }
        }
    }

    public function loadMore(): void
    {
        $this->paginate_var += 10;

        $this->loadMessages();
        $this->dispatchBrowserEvent('update-chat-height');
    }

    public function loadMessages()
    {
        $count = Message::where('conversation_id', $this->selectedConversation->id)->count();

        $this->loadedMessages = Message::where('conversation_id', $this->selectedConversation->id)
            ->skip($count - $this->paginate_var)
            ->take($this->paginate_var)
            ->get();


        return $this->loadedMessages;
    }

    public function sendMessage()
    {
        $this->validate([
            'body' => 'nullable|string|max:1700',
            'files.*' => 'nullable|image|max:10240', // 10MB Max per image
        ]);

        $messageData = [
            'conversation_id' => $this->selectedConversation->id,
            'sender_id' => auth()->id(),
            'receiver_id' => $this->selectedConversation->getReceiver()->id,
            'body' => $this->body,
        ];

        if ($this->files) {
            $filePaths = [];
            foreach ($this->files as $file) {
                $filePath = $file->store("photosMessage/{$this->selectedConversation->id}", 'public');
                $filePaths[] = $filePath;
            }
            $messageData['file_path'] = json_encode($filePaths); // Corrected attribute name
        }

        $createdMessage = Message::create($messageData);

        // Сбрасываем поле body и file после отправки сообщения
        $this->reset('body', 'files');

        // Посылаем событие для прокрутки вниз в интерфейсе чата
        $this->dispatchBrowserEvent('scroll-bottom');

        // Добавляем созданное сообщение в список загруженных сообщений
        $this->loadedMessages->push($createdMessage);

        // Обновляем время последнего обновления разговора
        $this->selectedConversation->updated_at = now();
        $this->selectedConversation->save();

        // Посылаем событие для обновления списка чатов (если нужно)
        $this->emitTo('chat.chat-list', 'refresh');

        // Отправляем уведомление получателю (если нужно)
        $this->selectedConversation->getReceiver()->notify(new MessageSent(
            auth()->user(),
            $createdMessage,
            $this->selectedConversation,
            $this->selectedConversation->getReceiver()->id
        ));
    }


    public function mount()
    {
        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.chat.chat-box');
    }
}
