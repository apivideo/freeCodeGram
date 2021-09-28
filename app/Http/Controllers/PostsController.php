<?php

namespace App\Http\Controllers;

use App\Post;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpClient\Psr18Client;
use ApiVideo\Client\Client;
use ApiVideo\Client\Model\VideoCreationPayload;
class PostsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $users = auth()->user()->following()->pluck('profiles.user_id');
        $posts = Post::whereIn('user_id', $users)->with('user')->latest()->paginate(5);

        return view('posts.index', compact('posts'));
    }

    public function create()
    {
        return view('posts.create');
    }

    public function store()
    {

        $extension = request('image')->extension();
        if ($extension == 'mp4' || $extension == 'mov') {
            $data = request()->validate([
                'caption' => 'required',
                'image' => ['required', 'mimes:mp4, mov']
            ]);

            $httpClient = new Psr18Client();
            $client = new Client(
                'https://ws.api.video',
                env('APP_APIVIDEO'),
                $httpClient
            );
            $file = request()->file('image');
            $fileName = $file->getClientOriginalName();
            $payload = (new VideoCreationPayload())
                ->setTitle($fileName);

            $video = $client->videos()->create($payload);

            $filePath = request()->file('image')->getRealPath();

            $response = $client->videos()->upload(
                $video->getVideoId(),
                new \SplFileObject($filePath)
            );

            $response = json_decode($response);
            $imagePath = $response->assets->player;

        }

        else {
            $data = request()->validate([
                'caption' => 'required',
                'image' => ['required', 'mimes:jpg, png'],
            ]);
            $imagePath = request('image')->store('uploads', 'public');

            $image = Image::make(public_path("storage/{$imagePath}"))->fit(1200, 1200);
            $image->save();
        }

        auth()->user()->posts()->create([
            'caption' => $data['caption'],
            'image' => $imagePath,
        ]);

        return redirect('/profile/' . auth()->user()->id);
    }

    public function show(\App\Post $post)
    {
        return view('posts.show', compact('post'));
    }
}
