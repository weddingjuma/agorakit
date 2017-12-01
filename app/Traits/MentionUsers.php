<?php

namespace App\Traits;

use App\Comment;
use Notification;

trait MentionUsers
{
    public static function bootMentionUsers()
    {
        static::created(function ($model) {
            if ($model instanceof Comment) { // we curently only support comments
                // Find users to notify
                $dom = new \DOMDocument();
                $dom->loadHTML($model->body);

                $users_to_mention = [];

                foreach ($dom->getElementsByTagName('a') as $tag) {
                    foreach ($tag->attributes as $attribName => $attribNodeVal) {
                        if ($attribName == 'data-mention-user-id') {
                            $users_to_mention[] = $tag->getAttribute($attribName);
                        }
                    }
                }
                // if we found some users to mention
                if (count($users_to_mention) > 0) {
                    $users = ($model->discussion->group->users->find($users_to_mention)); // we find users only in the group from where the mention appered to avoid abuse
                    Notification::send($users, new \App\Notifications\Mention($model, \Auth::user()));
                }
            }
        });
    }
}
