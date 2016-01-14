<?php

namespace App\Http\Controllers;

use App\Group;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Image;
use File;
use Auth;
use Storage;
use Gate;

class FileController extends Controller
{

  public function __construct()
  {
    $this->middleware('member', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
    $this->middleware('verified', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
    $this->middleware('cache', ['only' => ['index', 'show']]);
  }

  /**
  * Display a listing of the resource.
  *
  * @return Response
  */
  public function index($id)
  {
    if ($id) {
      $group = Group::findOrFail($id);
      $files = $group->files()->with('user')->orderBy('updated_at', 'desc')->paginate(20);

      $upload_allowed = $group->isMember();

      return view('files.index')
      ->with('files', $files)
      ->with('group', $group)
      ->with('tab', 'files')
      ->with('upload_allowed', $upload_allowed);
    }
  }


  public function gallery($id)
  {
    if ($id) {
      $group = Group::findOrFail($id);
      $files = $group->files()
      ->with('user')
      ->where('mime', 'like', 'image/jpeg') // TODO index on DB ? Failproof ?
      ->orWhere('mime', 'like', 'image/png')
      ->orWhere('mime', 'like', 'image/gif')
      ->orderBy('updated_at', 'desc')
      ->paginate(100);

      $upload_allowed = $group->isMember();

      return view('files.gallery')
      ->with('files', $files)
      ->with('group', $group)
      ->with('tab', 'files')
      ->with('upload_allowed', $upload_allowed);
    }
  }


  /**
  * Show the form for creating a new resource.
  *
  * @return Response
  */
  public function create($id)
  {
    if ($id) {
      $group = Group::findOrFail($id);

      $upload_allowed = $group->isMember();

      return view('files.create')
      ->with('group', $group)
      ->with('tab', 'files')
      ->with('upload_allowed', $upload_allowed);
    }
  }

  /**
  * Store a newly created resource in storage.
  *
  * @return Response
  */
  public function store(Request $request, $id)
  {
    try {
      $file = new \App\File();

      // we save it first to get an ID from the database, it will later be used to generate a unique filename.
      $file->forceSave(); // we bypass autovalidation, since we don't have a complete model yet, but we *need* an id

      // add group
      $file->group()->associate(Group::findOrFail($id));

      $file->user()->associate(Auth::user());

      // generate filenames and path
      $filepath = '/groups/'.$file->group->id.'/files/';


      $filename = $file->id . '.' . strtolower($request->file('file')->getClientOriginalExtension());

      // resize big images only if they are png, gif or jpeg
      if (in_array ($request->file('file')->getClientMimeType(), ['image/jpeg', 'image/png', 'image/gif']))
      {
        Storage::disk('local')->makeDirectory($filepath);
        Image::make($request->file('file'))->widen(1200, function ($constraint) {
          $constraint->upsize();
        })
        ->save(storage_path().'/app/' . $filepath.$filename);
      }
      else
      {
        // store the file
        Storage::disk('local')->put($filepath.$filename,  File::get($request->file('file')));
      }




      // add path and other infos to the file record on DB
      $file->path = $filepath.$filename;
      $file->name = $request->file('file')->getClientOriginalName();
      $file->original_filename = $request->file('file')->getClientOriginalName();
      $file->mime = $request->file('file')->getClientMimeType();

      // save it again
      $file->save();



      if ($request->ajax()) {
        return response()->json('success', 200);
      } else {
        return redirect()->back()->with('message', 'File was uploaded successfuly');
      }
    } catch (Exception $e) {

      if ($request->ajax()) {
        return response()->json($e->getMessage(), 400);
      }
      else {
        abort(400, $e->getMessage());
      }
    }
  }

  /**
  * Display the specified resource.
  *
  * @param int $id
  *
  * @return Response
  */
  public function show($group_id, $file_id)
  {
    $entry = \App\File::findOrFail($file_id);

    if (Storage::exists($entry->path)) {
      return (new Response(Storage::get($entry->path), 200))
      ->header('Content-Type', $entry->mime);
    } else {
      abort(404, 'File not found in storage at '.$entry->path);
    }
  }

  public function thumbnail($group_id, $file_id)
  {
    $entry = \App\File::findOrFail($file_id);

    if (in_array($entry->mime, ['image/jpeg', 'image/png', 'image/gif']))
    {
      $cachedImage = Image::cache(function($img) use ($entry) {
        return $img->make(storage_path().'/app/'.$entry->path)->fit(32, 32);
      }, 60000, true);

      return $cachedImage->response();
    }
    else
    {
      $img = Image::make(public_path().'/images/extensions/text-file.png')->fit(32, 32);
      return $img->response('jpg');
    }
  }


  public function preview($group_id, $file_id)
  {
    $entry = \App\File::findOrFail($file_id);

    if (in_array($entry->mime, ['image/jpeg', 'image/png', 'image/gif']))
    {
      $cachedImage = Image::cache(function($img) use ($entry) {
        return $img->make(storage_path().'/app/'.$entry->path)->fit(250,250);
      }, 60000, true);

      return $cachedImage->response();
    }
    else
    {
      $img = Image::make(public_path().'/images/extensions/text-file.png')->fit(64, 64);
      return $img->response('jpg');
    }
  }


  /**
  * Show the form for editing the specified resource.
  *
  * @param int $id
  *
  * @return Response
  */
  public function edit($id)
  {
  }

  /**
  * Update the specified resource in storage.
  *
  * @param int $id
  *
  * @return Response
  */
  public function update($id)
  {
  }




  public function destroyConfirm(Request $request, $group_id, $file_id)
  {
    $file = \App\File::findOrFail($file_id);
    $group = \App\Group::findOrFail($group_id);

    if (Gate::allows('delete', $file))
    {
      return view('files.delete')
      ->with('group', $group)
      ->with('file', $file)
      ->with('tab', 'file');
    }
    else
    {
      abort(403);
    }

  }



  /**
  * Remove the specified resource from storage.
  *
  * @param int $id
  *
  * @return \Illuminate\Http\Response
  */
  public function destroy(Request $request, $group_id, $file_id)
  {
    $file = \App\File::findOrFail($file_id);

    if (Gate::allows('delete', $file))
    {
      $file->delete();
      $request->session()->flash('message', trans('messages.ressource_deleted_successfully'));
      return redirect()->action('FileController@index', [$group_id]);
    }
    else
    {
      abort(403);
    }
  }
}
