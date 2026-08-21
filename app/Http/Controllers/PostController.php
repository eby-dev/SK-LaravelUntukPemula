<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Validation\ValidatesRequests;

class PostController extends Controller
{
    use ValidatesRequests;

    /**
     * index
     * 
     * @return void
     */
    public function index()
    {
        //get posts
        $posts = Post::latest()->paginate(5);

        //render view with posts
        return view('posts.index', compact('posts'));
    }

    /**
     * create
     * 
     * @return void
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * store 
     * 
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //validate form
        $this->validate($request, [
            'image'     => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'title'     => 'required|min:5|max:200',
            'content'   => 'required|min:10|max:50000'
        ]);

        //upload image
        $image = $request->file('image');
        $image->storeAs('posts', $image->hashName(), 'public');

        //create post
        Post::create([
            'image'     => $image->hashName(),
            'title'     => $request->title,
            'content'   => $request->content
        ]);

        //redirect to index
        return redirect()->route('posts.index')->with(['success' => 'Data Berhasil Disimpan!']);
    }

    /**
     * show
     * 
     * @param mixed $post
     * @return void
     */
    public function show(Post $post)
    {
        //return view
        return view('posts.show', compact('post'));
    }

    /**
     * edit
     * 
     * @param mixed $post
     * @return void
     */
    public function edit(Post $post)
    {
        return view('posts.edit', compact('post'));
    }

    /**
     * update
     * 
     * @param mixed $request
     * @param mixed $post
     * @return void
     */
    public function update(Request $request, Post $post)
    {
        //validate form
        $this->validate($request, [
            'image'     => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'title'     => 'required|min:5|max:200',
            'content'   => 'required|min:10|max:50000'
        ]);

        //check if image is uploaded
        if ($request->hasFile('image')) {

            //upload new image
            $image = $request->file('image');
            //$image->storeAs('public/posts', $image->hashName());
            $image->storeAs('posts', $image->hashName(), 'public');

            //delete old image
            //Storage::delete('public/posts/'.$post->image);
            Storage::disk('public')->delete('posts/' . $post->image);

            //update post with new image
            $post->update([
                'image'     => $image->hashName(),
                'title'     => $request->title,
                'content'   => $request->content
            ]);

        } else {

            //update post without image
            $post->update([
                'title'     => $request->title,
                'content'   => $request->content
            ]);
        }

        //redirect to index
        return redirect()->route('posts.index')->with(['success' => 'Data Berhasil Diubah!']);
    }

    /**
     * destroy
     * 
     * @param mixed $post
     * @return void
     */
    public function destroy(Post $post)
    {
        //delete image
        //Storage::delete('public/posts/'.$post->image);
        Storage::disk('public')->delete('posts/' . $post->image);

        //delete post
        $post->delete();

        //redirect to index
        return redirect()->route('posts.index')->with(['success' => 'Data Berhasil Dihapus!']);
    }
}
