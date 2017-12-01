<?php

namespace App;

use App\Traits\LogsActivity;
use Cviebrock\EloquentTaggable\Taggable;
use Iatstuti\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Venturecraft\Revisionable\RevisionableTrait;
use Watson\Validating\ValidatingTrait;

class Discussion extends Model
{
    use RevisionableTrait;
    use ValidatingTrait;
    use SoftDeletes;
    use CascadeSoftDeletes;
    use Taggable;
    use LogsActivity;

    protected $cascadeDeletes = ['comments'];

    protected $touches = ['group', 'user'];

    protected $rules = [
    'name'     => 'required|min:5',
    'body'     => 'required|min:5',
    'user_id'  => 'required|exists:users,id',
    'group_id' => 'required|exists:groups,id',
  ];

    protected $dontKeepRevisionOf = ['total_comments'];

    protected $table = 'discussions';
    protected $fillable = ['name', 'body', 'group_id'];

    public $timestamps = true;

    public $unreadcounter;

    public $read_comments;

    protected $dates = ['deleted_at'];

    protected $casts = ['user_id' => 'integer'];

    public function unReadCount()
    {
        if (\Auth::guest()) {
            return 0;
        }

        if ($this->userReadDiscussion->count() > 0) {
            return $this->total_comments - $this->userReadDiscussion->first()->read_comments;
        }

        return $this->total_comments;
    }

    public function group()
    {
        return $this->belongsTo('App\Group');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function votes()
    {
        return $this->hasMany('App\Vote');
    }

    public function comments()
    {
        return $this->hasMany('App\Comment');
    }

    public function userReadDiscussion()
    {
        if (\Auth::check()) {
            return $this->hasMany('App\UserReadDiscussion')->where('user_id', '=', \Auth::user()->id);
        } else {
            abort(500, 'Need to be logged in to access this userReadDiscussion relation');
        }
    }

    public function link()
    {
        return route('groups.discussions.show', [$this->group, $this]);
    }
}
